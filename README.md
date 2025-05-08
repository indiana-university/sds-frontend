# scholarly-data-share
This repository provides the code to deploy the Scholarly Data Share service (SDS). The current version of SDS is based on Omeka S 4.1.1 (information on Omeka S releases is available here: https://github.com/omeka/omeka-s/releases).


# Installing and configuring Omeka S

Please review the resources needed to run Omeka S (https://omeka.org/s/docs/user-manual/install/) and confirm you have all resources available prior to starting the install process.

## Setup a MySQL Database for Omeka S
1. In your MySQL instance, create a new empty schema for the Omeka database.
2. Create a user and grant that user the following permissions on the new schema:
	SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,REFERENCES,ALTER,INDEX
	
## Setup Omeka S Core Server Files

1. Download Omeka S from the Omeka repository (https://omeka.org/s/).
2. Replace the directories containing SDS's modifications to Omeka S from this repo. See [README](./omeka/README.md) for details.
3. Fill in the database connection information in config/database.ini (host, database username, database name) and confirm the port number is correct for your system (if not, update it). Save and close the file.
4. Open the .htaccess file and change the entry in the first line from 'SetEnv APPLICATION_ENV "production"' to 'SetEnv APPLICATION_ENV "development"' so that Omeka will provide more information on any errors you encounter during setup. Once the site dev is complete, change it back to production before taking the site live.
5. Upload the SDS core files to your server.
6. Navigate in your browser to the url for your site. This will take you to the Omeka install page.
7. Fill in the site information (title, admin account, etc.) then click "Submit".
Omeka should now be installed and will have created the necessary databases in your schema.
8. Login to the admin site (the login for the admin site is [yoursiteurl]/admin)
9. Add necessary users to the site with appropriate roles as needed using the "User" tab under the ADMIN menu in the left sidebar.
10. Navigate in the admin site to "Modules" then activate the following modules:
- BulkExport
- CSVExport
- CSVImport
- CustomOntology
- HideProperties
- Log
- PageBlocks (IURT version download request tracking component, creates userjobs table in the database)
- NumericDataTypes
- Restricted Sites (if your instance requires user-level authorization to access data)
- Shortcode
- Sitemaps

8. Point the instance to your server for downloads. In the server files, navigate to application/view/omeka/site/item and open show.phtml in a text or code editor.
- In line 149, replace 'downloadurl' with the address of the VM that will handle file downloads.
- In line 151, replace 'loginURL' with the login url for your site.

9. Add your database settings to the "My Downloads" section of the Page Blocks module in modules/PageBlocks/view/common/block-layout/mydownloads.phtml in line 10.

10. Configure the OIDC Module
- Navigate to [Omeka-s-module-OIDC](https://indiana-university/omeka-s-module-oidc) and follow the instructions to download and install the OIDC module.
- In config/local.config.php, add the client_id and client_secret for your instance (see module documentation).
- In the Omeka admin site, go to Modules-> OIDC and enter the 'Discover Document URL' for your OIDC connection.

## Site Setup and Configuration

### Vocabularies
1. Import the Vocabularies/Ontologies needed for your instance.
- These could be existing ontologies required for your data in addition to those included with Omeka S (Dublin Core, Dublin Core Type, Bibliographic Ontology, and Friend of a Friend).
  - To import an existing ontology, navigate to Vocabularies in the left sidebar, then choose "Import new vocabulary" in the top right of the Vocabularies page. This option requires a vocabulary file, Namespace URI, and Namespace prefix.
  - If you are working with 3D data and plan to use the accordion-style metadata structure used for the IU3D Share, you can import the SDS IU3D Ontology into your instance (https://iu3d.sds.uits.iu.edu/ns).
- You can also create and import your own custom Ontology using the "Custom Ontology" module. Information about using this module is available at the GitLab page for the module (https://gitlab.com/Daniel-KMs).

### Design Resource Templates and Item Sets
2. Navigate to 'Resource templates' in the left sidebar. Create the templates needed for your SDS instance. 
- The default metadata format in SDS lists all fields with data in one column with Element headings on the same line as the Element text.
3. Navigate to 'Item sets' in the left sidebar. Create the collections needed for your instance and fill in the desired metadata fields for each collection.
- Collections (Item Sets) can be created manually individually in Omeka S or can be imported as a csv.

### Create One or More Sites for Your Instance
4. Create one or more Sites for your SDS instances by clicking 'Sites' in the left sidebar then choosing "Add new site." Fill out the site information (title, etc.) and choose the appropriate settings for the site.
5. Add pages to your site by selecting the site from the admin site list then clicking "Pages" in the Site's menu in the left sidebar. Create any new pages needed for the site and populate them with their content, choosing to add them to the navigation menu as appropriate. 
6. For SDS, you will need to have a page named "My Downloads" with these features/components:
  - Slug: mydownloads
  - Add an html block with text explaining how long downloads will be available for after processing and any other information your users need to know about tracking and accessing Downloads using the page.
  - Add a MyDownloads Block
7. To edit the navigation menu for a site, click the 'Navigation' tab in the site's sidebar menu. Add pages to the menu and order them as needed.
8. Choose a home page from the dropdown selection on the right side of the Navigation page if you do not want to use the default home page.
9. In each site you create, set the following settings:
  - Settings -> General -> Auto-assign new items: Leave checked if you want all new items auto assigned, otherwise uncheck
  - Settings -> General -> Show user bar on public views: Always
  - Settings -> Search -> Advanced search vocabulary members: Used by resources in this site.
  - Settings -> Sitemaps -> Enable dynamic sitemap for this site: check to enable

## Ingest Datasets
1. Navigate to 'CSV Import' in the left sidebar.
2. Use the importer to import each dataset (Note that only one Resource Template can be imported at once).
3. Prior to import, confirm your column headings correspond to the auto-recognize format (Item Type Metadata: Date, etc.)
4. If you find an error in an import, that entire import can be undone by navigating to 'Status' tab of the import page then clicking 'Undo' next to the import you want to undo.



## Optional Omeka S Design Components for SDS

- Adding IU Branding to an SDS Instance ()
- Adding a grouped, accordion-style, metadata structure ()
- Adding the option to have multi-part downloads for individual items in SDS ()



# Installing and Configuring the Middleware
Visit the [sds-middleware repo](https://github.com/indiana-university/sds-middleware) for information on deploying the middleware.
