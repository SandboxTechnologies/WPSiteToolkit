WPSiteToolkit
=============

A PHP script to aid migration and other aspects of a Wordpress install.

The WPSiteToolkit differs from other migration/helper scripts in that it uses the Wordpress database settings already on the host site. This helps to reduce the amount of configuration required and focuses more on features.

### Installation/Removal
Follow these instructions to upload an unmodified version of the script to your website for use.

1. Make sure you have uploaded/moved both Wordpress website files and database.
2. Upload the main script file 'WPSiteToolkit.php' to the main website root directory of the destination Wordpress site.
3. Test the script to see if it loads by using http://your-site-domain/WPSiteToolkit.php
4. You should see that the page loads and tells you that it will not run unless you change the secure word. Do this by creating a secure word (or copying the random example that the page gives) and replacing the default word at the top of the script.
5. Now when you load the page, you should see a secure word field. Enter the secure word you added in the script and hit submit. You should now see the available tools, and the secure word field will be used to verify any subsequent page submissions.

If you want to remove the script simply delete the file WPSiteToolkit.php

### Database tools
The database tools currently focus purely on find and replace since this was the primary reason for the creation of the script to begin with.
Follow these instructions to find text and replace it within the database tables.

Important note: ***MAKE SURE YOU HAVE A CURRENT BACKUP OF YOUR DATABASE BEFORE USING THESE TOOLS***

1. Select the database tables you want to run the find/replace on.
2. Enter the text to find in the first text column, and the corresponding text to replace in the 2ns column. If you would like to automatically add the current hosts domain and website root, click the Auto Fill Current Host button.
3. To verify and perform a non-destructive search, click on submit. You should see a short summary for the text and table parameters you have chosen.
4. To perform a destructive write to the database with the changes, check the box Commit Changes and then click submit.
