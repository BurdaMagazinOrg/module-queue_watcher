CONTENTS OF THIS FILE
---------------------
   
 * Introduction
 * Installation
 * Available drush commands

INTRODUCTION
------------

The Queue Watcher module lets you define automatic checks
for specific queues regarding their overall size.

A queue might get bloated due to missing or too slow worker jobs.
With this module, you can define size limits a queue shouldn't exceed.
During each cron run, the Queue Watcher checks the sizes of the queues
and sends reports to certain E-Mail addresses
and the logging system in case of exceeded limits.

The module also adds some Drush helper commands, e.g.
<code>$ drush queue-watcher-lookup</code>

.. to get a list of currently existent queues with their states and for optionally sending reports to the configured recipients (mails and/or logs).

Furthermore you can see a status summary of your queue states in the status report.

INSTALLATION
------------

Make sure your cron is running properly.

Install the module itself as usual, see
https://www.drupal.org/docs/8/extending-drupal-8/installing-contributed-modules-find-import-enable-configure-drupal-8.

Configure your queue sizes and target report addresses on
admin/config/queue-watcher

AVAILABLE DRUSH COMMANDS
------------------------

<code>$ drush queue-watcher-lookup</code>

.. performs a lookup and shows information of the given queue.
See <code>drush help queue-watcher-lookup</code> for additional usage information.
