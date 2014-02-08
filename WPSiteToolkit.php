<?php

/**
 * WPSiteToolkit
 *
 * This script provides easy tools to modify and report on aspects of a Wordpress install
 * (see readme file for installation and usage)
 *
 * USE OF THIS SCRIPT IS ENTIRELY AT YOUR OWN RISK
 *
 * @package     WPSiteToolkit
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GENERAL PUBLIC LICENSE v3
 * @author      Mike Snow <dev@sandboxtechnologies.co.uk>
 * @copyright   2014 Sandbox Technologies LTD
 * @link        https://github.com/SandboxTechnologies/WPSiteToolkit
*/

/**
 * Add security here by replacing yoursecureword with your own secure word
 * then use that word on the script page to show the available tools.
 */
define('SECUREWORD', 'yoursecureword');

// load Wordpress dependencies
require( dirname(__FILE__) . '/wp-load.php' );

class WPSiteToolkit {

    protected $wpdb;

    protected $debug;

    protected $dbTables;
    protected $dbOptions;
    protected $dbResult;
    protected $dbCommit;

    const TITLE = 'WordPress Site Toolkit';
    const FIND_MIN_LENGTH = 5;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->debug = isset($config['debug']) ? true : false;

        $this->dbCommit = isset($_POST['database-commit']) ? true : false;
        $this->dbTables = $this->getAllTables();

        $this->dbOptions = array('find' => (isset($_POST['database-find'])) ? array_filter($_POST['database-find']) : array(), 'replace' => (isset($_POST['database-replace'])) ? $_POST['database-replace'] : array(), 'tables' => (isset($_POST['database-tables'])) ? $_POST['database-tables'] : $this->dbTables);
        $this->dbResult = array_fill_keys($this->dbOptions['find'], array_fill_keys($this->dbTables, 0));
    }

    public function getAllTables()
    {
        if (!is_null($this->dbTables)) {
            return $this->dbTables;
        }

        $this->log('Gathering tables');
        $result = array();

        $myrows = $this->wpdb->get_results("SHOW TABLES", ARRAY_A);

        foreach ($myrows as $row) {
            $result[] = current($row);
        }

        return $result;
    }

    public function getOption($type, $index)
    {
        if (!is_null($this->dbOptions[$type]) && isset($this->dbOptions[$type][$index]) ) {
            return $this->dbOptions[$type][$index];
        }
        return '';
    }

    public function getOptions($type)
    {
        if (is_array($this->dbOptions[$type]) ) {
            return $this->dbOptions[$type];
        }
        return array();
    }

    public function isDbCommitted()
    {
        return $this->dbCommit;
    }

    public function run()
    {
        foreach ($this->dbOptions['tables'] as $table) {
            $this->processTable($table);
        }
    }

    protected function processTable($table)
    {
        $this->log('Processing table', $table);
        //$result = array();

        $myrows = $this->wpdb->get_results("SELECT * FROM $table", ARRAY_A);

        if (count($myrows) == 0) {
            $this->log('No rows in table', $table);
            return;
        }

        foreach ($myrows as $row) {
            reset($row);
            $rowData = array();
            $rowData['idKey'] = key($row);
            $rowData['idValue'] = array_shift($row);
            $rowData['fields'] = $row;

            $this->performChecks($table, $rowData);
        }
    }

    protected function performChecks($table, $rowData)
    {
        $this->log('Performing checks on row', $rowData['idKey'] . " (" . $rowData['idValue'] . ")");

        foreach ($rowData['fields'] as $fieldKey => $fieldValue) {
            $this->findAll($table, $rowData['idKey'], $rowData['idValue'], $fieldKey, $fieldValue);
        }
    }

    protected function findAll($table, $idKey, $idValue, $fieldKey, $fieldValue)
    {
        $workingFieldValue = $fieldValue;

        foreach ($this->dbOptions['find'] as $key => $value) {
            $find = trim($value);
            $replace = $this->dbOptions['replace'][$key];

            if (empty($find) || strlen($find) < self::FIND_MIN_LENGTH)
                continue;

            if ($this->findSingle($find, $fieldKey, $fieldValue)) {
                $workingFieldValue = $this->findReplace($table, $idKey, $idValue, $fieldKey, $workingFieldValue, $find, $replace);
            }
        }

        // if working field is different then commit to db
        if ($workingFieldValue !== $fieldValue && $this->dbCommit) {
            //var_dump($table, $idKey, $idValue, $fieldKey, $fieldValue, $workingFieldValue);
            $this->dbWrite($table, $idKey, $idValue, $fieldKey, $workingFieldValue);
        } else {
            $this->log('Skipped updating table field row', $fieldKey);
        }
    }

    protected function findSingle($find, $fieldKey, $fieldValue)
    {
        if (strpos($fieldValue, $find) === false) {
            return false;
        } else {
            $this->log('Found string in field', $fieldKey . " ($find) $fieldValue");
            return true;
        }
    }

    protected function findReplace($table, $idKey, $idValue, $fieldKey, $fieldValue, $find, $replace)
    {
        // note the number of occurrences to the result array
        $this->dbResult[$find][$table] += substr_count($fieldValue, $find);

        if (is_serialized($fieldValue)) {
            $newFieldValue = self::strReplaceSerialize($find, $replace, $fieldValue);
        } else {
            $newFieldValue = str_replace($find, $replace, $fieldValue);
        }

        return $newFieldValue;
    }

    static public function strReplaceSerialize($find, $replace, $fieldValue) {
        $count = preg_match_all('/s:([0-9]+):"(.*?)";/s', $fieldValue, $matches);
        $findArray = array();
        $replaceArray = array();

        if ($count > 0) {
            foreach($matches[0] as $key => $match) {
                if (substr_count($matches[2][$key], $find) > 0) {
                    $newFieldValue = str_replace($find, $replace, $matches[2][$key]);
                    $findArray[] = $match;
                    $replaceArray[] = sprintf('s:%u:"%s";', strlen($newFieldValue), $newFieldValue);
                }
            }
        }
        return str_replace($findArray, $replaceArray, $fieldValue);
    }

    protected function dbWrite($table, $idKey, $idValue, $fieldKey, $fieldValue) {
        $result = $this->wpdb->update($table, array($fieldKey => $fieldValue), array($idKey => $idValue), array('%s'));

        if ($result === false) {
            $this->log('Error updating table field row', $fieldKey);
            exit;
        } else {
            $this->log('Successfully updated table field row', $fieldKey);
        }
    }

    protected function searchFile($filename, $string) {
        $handle = fopen($filename, 'r');
        $result = false;

        while (($buffer = fgets($handle)) !== false) {
            if (strpos($buffer, $string) !== false) {
                $result = true;
                break; // if we found it, break out of the loop
            }
        }
        fclose($handle);

        return $result;
    }

    public function getDbResults() {
        foreach ($this->dbResult as $key => $value) {
            $this->dbResult[$key] = array_filter($value);
        }
        return $this->dbResult;
    }

    public function log($action, $details = '')
    {
        if ($this->debug)
            echo $action . ': ' . $details . PHP_EOL;
    }

}

class WPSiteToolkitSecure {

    public $secureWord;
    protected $postWord;

    const PASSWORD_LENGTH = 14;
    const PASSWORD_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function __construct()
    {
        $this->postWord = (isset($_POST['secure-word'])) ? $_POST['secure-word'] : '';

            if (isset($_SERVER['WP_SITE_TOOLKIT_SECURE_WORD'])) {
                $this->secureWord = $_SERVER['WP_SITE_TOOLKIT_SECURE_WORD'];
            } else {
                $this->secureWord = SECUREWORD;
            }
    }

    public function secured() {
        return ($this->secureWord == 'yoursecureword') ? false : true ;
    }

    public function authenticated() {
        return ($this->postWord == $this->secureWord) ? true : false ;
    }

    public function getCurrentWord() {
        return $this->postWord;
    }

    function generatePassword($length = self::PASSWORD_LENGTH) {
        $chars = self::PASSWORD_CHARS;
        $count = mb_strlen($chars);

        for ($i = 0, $result = ''; $i < $length; $i++) {
            $index = rand(0, $count - 1);
            $result .= mb_substr($chars, $index, 1);
        }

        return $result;
    }
}

// create the security instance
$security = new WPSiteToolkitSecure();

// on authentication, create the main toolkit instance
if ($security->authenticated()) {
    $toolkit = new WPSiteToolkit();
    // run the db procedure
    if (isset($_POST['database-submit'])) {
        $toolkit->run();
    }
}

/*


filesystem
- fields to find
- check writable directories/files
- add/show htaccess to protect upload directories?
submit



 */

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html(WPSiteToolkit::TITLE); ?></title>
        <meta name="viewport" content="width=device-width">
        <link rel='stylesheet' href='/wp-admin/load-styles.php?c=0&amp;dir=ltr&amp;load=dashicons,admin-bar,wp-admin,buttons,wp-auth-check&amp;ver=<?php echo get_bloginfo('version'); ?>' type='text/css' media='all' />
        <link rel='stylesheet' id='open-sans-css'  href='//fonts.googleapis.com/css?family=Open+Sans%3A300italic%2C400italic%2C600italic%2C300%2C400%2C600&#038;subset=latin%2Clatin-ext&#038;ver=<?php echo get_bloginfo('version'); ?>' type='text/css' media='all' />
        <link rel='stylesheet' id='colors-css'  href='/wp-admin/css/colors.min.css?ver=<?php echo get_bloginfo('version'); ?>' type='text/css' media='all' />
        <style type="text/css">html,body{height:auto}.wrap{margin: 20px;}</style>
        <script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5B%5D=jquery-core,jquery-migrate,utils&amp;ver=<?php echo get_bloginfo('version'); ?>'></script>
        <script type="text/javascript">
            jQuery(function() {
                jQuery( ":button[name='autofill-host']" ).on( "click", function() {
                    jQuery("input[name='database-replace[]']").each(function(index) {
                        if (index == 0) jQuery(this).val(jQuery("input[name='document-root']").val());
                        if (index == 1) jQuery(this).val(jQuery("input[name='server-name']").val());
                    });
                });
            });
        </script>
    </head>
    <body class="wp-admin wp-core-ui no-js toplevel_page_WordPress-Admin-Style-master-wp-admin-style auto-fold admin-bar branch-3-8 version-3-8-1 admin-color-fresh locale-en-us no-customize-support no-svg">

        <div class="wrap">

            <h1><?php echo esc_html(WPSiteToolkit::TITLE); ?></h1>

            <h2>Security</h2>

<?php if (!$security->secured()): ?>
            <div class="error">
                <p>This page will not run unless you change the secure word. Example: <strong><?php echo $security->generatePassword(); ?></strong>. See script for details.</p>
            </div>
<?php else: ?>

            <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post" autocomplete="off">

<?php if ($security->secured()): ?>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row">Secure Word</th>
                            <td><input name="secure-word" id="" type="text" value="<?php echo esc_html($security->getCurrentWord()); ?>" class="regular-text" /></td>
                        </tr>
                    </tbody>
                </table>
<?php endif; ?>

<?php if ($security->authenticated()): ?>
                <div class="error">
                    <p><strong>Always backup your database or files before committing actions that can potentially cause irreversible harm.</strong></p>
                </div>

                <h2>Database Tools</h2>

                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row">
                                Database Tables
                                <p></p>
                            </th>
                            <td>
                                <select name="database-tables[]" id="" multiple="multiple" size="<?php echo count($toolkit->getAllTables()); ?>">
<?php foreach($toolkit->getAllTables() as $table): ?>
                                        <option value="<?php echo esc_html($table); ?>"<?php if (in_array($table, $toolkit->getOptions('tables'))) echo ' selected="selected"'; ?>><?php echo esc_html($table); ?></option>
<?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row">
                                Find/Replace
                                <p><button class="button-secondary" type="button" name="autofill-host">Auto Fill Current Host</button></p></th>
                            <td>
<?php for($count=0;$count<3;$count++) { ?>
                                <input name="database-find[]" id="" type="text" value="<?php echo esc_html($toolkit->getOption('find', $count)); ?>" class="all-options" />
                                <input name="database-replace[]" id="" type="text" value="<?php echo esc_html($toolkit->getOption('replace', $count)); ?>" class="all-options" />
                                <br />
<?php } ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Commit Changes</th>
                            <td><input name="database-commit" type="checkbox" id="" value="1"/></td>
                        </tr>
                    </tbody>
                </table>
<?php if($toolkit->getDbResults()): ?>
                <div class="updated">
<?php foreach($toolkit->getDbResults() as $find => $tables): ?>
                    <p>Searched for: <strong>"<?php echo esc_html($find); ?>"</strong>
<?php foreach($tables as $table => $count): ?>
                        <br /><em>Found <?php echo esc_html($count); ?> instances in table: <?php echo esc_html($table); ?></em>
<?php endforeach; ?>
<?php if(empty($tables)): ?>
                        <br /><em>Found 0 instances</em>
<?php endif; ?>
                    </p>
<?php endforeach; ?>
<?php if($toolkit->isDbCommitted()): ?>
                    <p><strong><em>Changes have been committed.</em></strong></p>
<?php endif; ?>
                </div>
<?php endif; ?>
<?php endif; ?>

            <input type="hidden" name="document-root" value="<?php echo esc_html($_SERVER["DOCUMENT_ROOT"]); ?>"/>
            <input type="hidden" name="server-name" value="<?php echo esc_html($_SERVER["SERVER_NAME"]); ?>"/>
            <input class="button-primary" type="submit" name="database-submit" value="Submit" />

            </form>

        </div><!-- .wrap -->

<?php endif; ?>

    </body>
</html>
