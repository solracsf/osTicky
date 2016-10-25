# osTicky - osTicket Bridge Extension for Joomla 3.x
osTicky *(osTicket Bridge)* by SmartCalc is a **Joomla 3.x** extension that provides Joomla fronted integration with **osTicket**, a popular Support ticket system.

osTicky version 2.x was significantly rewritten in order to provide support for the latest osTicket version 1.9.x.

**Main changes in the new version are:**

* Basic support of osTicket 1.9.x, including user-defined forms, fields and lists
* Rich-text (HTML) thread messages and emails support
* Multiple file attachments per message/ticket *(according to osTicket admin settings)*
* Improved form interface - custom field placeholders, hints, selection prompts, etc., reading configuration from osTicket forms settings
* Custom fields values can be referenced in email templates when sending alerts and auto-responses

## There are some important limitations in osTicky. Please note:

- If you plan to use osTicket 1.x plugins and/or modify osTicket core behavior, there is **no guarantee** that it will not break the interaction with osTicky. It fully depends on the nature and functions of these plugins/mods.

- osTicky 2.x uses database to store attachments, the same way original osTicket 1.x does by default. If you install a plugin that changes the way the attachments are saved *(e.g. to "file system")* osTicky will continue saving and trying to read attachments from database tables, which will lead to unpredictable results.

## osTicket 1.9 features missing or partially implemented in osTicky:

- **Knowledge base features are not supported in osTicky.** osTicky is a component centered in creating, viewing and responding to support tickets only.

- **Frontend ticket edit, _i.e. ticket modification by author,_ implemented in osTicket 1.8 as an option, is not supported.** Even if set so in osTicket configuration *(this is not the default setting, though)* it will have no effect in osTicky. Once the ticket is created, it cannot be edited in osTicky for Joomla.

- **Ticket filters are partially implemented in osTicky.** The only filter function supported is *"ticket reject"* based on the filters configured by osTicket administrator. There is another limitation: only filters that have *"match all"* property set to *"No"* are taken into account. If you create a multi-rule filter with *"match all"* set to *"Yes"*, it will have no effect in osTicky. Keeping in mind that the only filter action implemented in osTicky is *"ticket reject"*, and reject rules are usually configured using *"OR"* logic *(i.e. reject ticket if any of the rules report a match)*, the overall filter behavior is likely to be similar to original osTicket. All other filters like automatic ticket assignment, department selection etc., based on the incoming ticket data, are **not supported**.

- **osTicket 1.9.X organizations feature is not supported.** When a user creates a ticket using an email belonging to an organization member, this ticket will be listed in organization view in osTicket staff interface, but osTicky will not do any automatic department/staff assignment, also no alerts will be sent to organization account manager, even if set so in osTicket options. This feature is **planned** in future versions of osTicky 2.x.

# osTicky Installation

A working installation of **osTicket 1.9.4 or later** is required before osTicky 2.x can be configured.

You will need the following data in order to configure osTicky:

* osTicket database host
* osTicket database name
* osTicket database username
* osTicket database password

osTicky supports both external *(a dedicated database)* and internal *(using Joomla database)* locations. Although a dedicated database is the standard and recommended storage solution for osTicket, some hosting providers put limits on the number of databases per account *(especially for free accounts)*. Installing osTicket to the same database where Joomla is installed will not increase the number of databases in use. On the other hand, external database should be accessible from the domain where Joomla *(and naturally osTicky)* is installed. This is not always as trivial as it seems to be. Many hosting providers do not allow database access from other domains *(mostly for free accounts)*. Even if you set all connection parameters correctly, database access may fail if osTicket is located on another domain.

Please, ask your hosting provider if you are having issues. Normally, if you use the same hosting account for both databases everything should work smoothly.

When you first install osTicky 2.x and go to `Components / osTicky2 (osTicket Bridge)`, you should see the following messages:

![Screen 1](http://smartcalc.org/images/osticky2/db_no_settings.png)

### Step 1

Click `Options` to open configuration screen. The default settings are:

![Screen 2](http://smartcalc.org/images/osticky2/db_details_no_settings.png)

The value displayed in `Configuration ID` dropdown indicates that osTicky is not configured yet *(when you save a correct configuration this dropdown will show the first configuration key available  - `core` for standard osTicket installation).* All fields on this screen are required.

* **Location:** if osTicket tables are located in Joomla database *(the same database where osTicky is installed)* - select `Joomla`, otherwise select `External`. When `Joomla` is selected as location, the values from the `External Database` fieldset will be ignored.
* **Table prefix:** normally `ost_` *(both for internal and external databases)* but if you have osTicket configured with another tables prefix, enter it here.
* **Configuration ID:** mostly used to indicate if database connection is successful. For standard osTicket installations select `core` from this list when available.

### External database settings:

* **Driver:** always `MySQLi`
* **Host:** osTicket `database host`
* **Username:** `database username`
* **Password:** `database password`
* **Name:** `database name`

The database user must have **CRUD** *(or higher)* permissions for osTicket database plus **DROP** permission for at least one table: `ost_ticket__cdata`.

This table plays a role of a temporary table in osTicket and is constantly recreated and dropped *(it makes ticket list queries faster)*. When a new ticket is created via osTicky web interface, this table should be dropped so that it can be recreated correctly when an osTicket staff member opens ticket list.

If the user configured as a database user in osTicky settings doesn't have this **DRO** privilege *(which is quite normal for security reasons)*, it is highly recommended to add **DRO** privilege at table level (`<your_osticket_database_name>.ost_ticket__cdata`). It will not compromise security *(due to the temporary nature of `ost_ticket__cdata` table)* but will make osTicky/osTicket integration smoother. 

Anyway, even if `DROP TABLE ost_ticket__cdata` query fails due to insufficient privileges, the ticket will be created and written to osTicket database. Perhaps, you will see a warning message.

Of course, if you access osTicket database as administrator *(i.e. configure a root user in database settings)* osTicky will have all permissions for osTicket database, but if you are concerned about security it is recommended to create a **special db user** for use with osTicky with the privileges listed above.

After you fill in all required fields, click `Save`. If the settings were correct, you should see the following screen *(username, password etc. values will differ)* - the screenshot below corresponds to `External` database option:

![Screen 3](http://smartcalc.org/images/osticky2/db_details_sample.png)

### Step 2

Go to the next pane - `osTicket frontend integration`

![Screen 4](http://smartcalc.org/images/osticky2/frontend_integration.png)

Default settings for all fields on this pane should work, but you can adjust the following settings:

- **HTML Editor:** Select an editor that will be used for ticket fields that expect HTML input *(if allowed in osTicket options)*. This setting is independent from the global Joomla html editor setting. This is done on purpose: Joomla global preferred editor is mostly used for creating articles and modifying this global setting will not affect osTicky. You have to configure Joomla `Text filters` in global configuration and allow HTML input for the usergroups allowed to create tickets. `Default black list` option is recommended if you plan to allow `Rich text tickets` in osTicket.

- **Send client's system information:** append client's browser and system short description to tickets. Default is `No`. Please, keep in mind that even if this setting is set to `Yes`, the user can disable it on the new ticket form before submitting ticket.

- **Preferred mode:** choose how client's system information *(and sticky ticket data, for sticky tickets)* is attached to tickets. This data can be appended to the ticket message or also added to the ticket as attachment. The latter option generates an attachment file that can be sent by email/file transfer from one staff member to another.

- **Sticky tickets help topics:** if you are using "Sticky" tickets *(osTicky system plugin is required)* you can set a fixed help topic for this kind of tickets *(optional)*.

- **Show private topics:** select usergroups that will be allowed to create tickets on osTicket help topics marked as private. Standard osTicket frontend will never show private topics but in Joomla frontend *(osTicky)* there is an option to allow certain users to create tickets on private topics. If you prefer to preserve original osTicket behavior, leave this option empty.

**List layout** fieldset contains standard Joomla list views settings and should be normally left as suggested by default settings.

### Step 3

The `Permissions` pane settings require modifications, otherwise only Super admins will be able to create tickets / respond in ticket thread, which is obviously not the desired behavior. First, you should decide if guests *(not logged in users)* have to be authorised to create tickets. The following screenshot shows a configuration allowing guests to create tickets:

![Screen 5](http://smartcalc.org/images/osticky2/permissions_public.png)

**Reply** action has no meaning for not logged in users. A user must have access to a ticket in order to be able to reply, but no tickets are visible until the user logins. This action permission is important for registered users *(or any other group considered as a group for logged in frontend users)*. The screenshot below shows a configuration when `Public` group is authorised to create tickets, so the `Create tickets` permission results in `Allowed` and is inherited. If you do not allow guests to create tickets, you should set both `Create tickets` and `Reply` permissions to `Allowed` explicitly.

![Screen 6](http://smartcalc.org/images/osticky2/permissions_registered.png)

It is also possible to configure permissions so that nobody *(except super admins)* will be authorised to create tickets, but logged in users will be able to post messages in ticket thread. Although functionally it looks like a really rare case.

Click `Save and Close`. If database connection options were introduced correcly at Step 1, the information view should read the following *(helpdesk title and helpdesk URL will be different)*:

![Screen 7](http://smartcalc.org/images/osticky2/db_ok.png)

### Step 4

Your site visitors will **not** be able to access osTicky component **until** you create menu items in Joomla Menu Manager. Normally, two items should be added, one to create a New Ticket and the other to View the user's Tickets. If access levels for these menu items are lower than permissions set on Step 3, the user will be redirected to login view and after sucsessful authorisation back to the view assigned to menu item.
