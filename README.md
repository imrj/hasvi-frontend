# hasvi-frontend
Frontend (Wordpress plugin) GUI for the Hasvi data storage and visualisation software

Hasvi (www.hasvi.com) is a storage and visualisation software package for Internet of Things (IOT) devices.

It consists of two parts:
- Hasvi-backend: The storage and visualisation of datastreams from IOT devices. It is designed to run on AWS.
- Hasvi-frontend: A Wordpress plugin for users to manage (add/edit/remove) their data streams.

Both software packages are required for a functioning Hasvi package.

## Installation and configuration:
Simply upload a zipped copy of this repository as a plugin in Wordpress.

Once installed and activated, go to the Hasvi menu on the admin menu and fill in all the fields. This includes:
- AWS Key and Region for access to the DynamoDB database holding the data tables
- URL to the Hasvi backend
- Whether it is running in debug or production mode (different sets of tables are used in each. The debug version uses tables prepended with "testing-"

At the bottom of the configuration page, a set of dianostics will show if Hasvi-frontend can successfully access all the tables and global indexes in DynamoDB, showing the number of each table and a "0" for each global index.

## Website configuration:
Use the following shortcodes to display the following information for the logged-in user:
- `[hd_aTable]` for general user account information
- `[hd_sTable]` for an editable table of data streams
- `[hd_vTable]` for an editable table of views

As error message will appear if a non-logged in user loads a pages containing any of these shortcodes

## User Management
The `useraccounts` table in DynamboDB contains the limits (max numbers of views and streams allowed, mininum time between adding new data to a stream) for each user. A user entry is automatically created when a new user account is created on the Wordpress site.

The limits of a particular user account can be viewed and edited in the Hasvi admin interface, under Hasvi->Users

