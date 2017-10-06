Xlsgd grade export
==================

This plugin exports grades in xls file, adding: 
1. Group column,  shows groups' name which students are enroled. In the "All participants" export (without selection of group), you'll see a row for each student-group couple, it means that for the same student could have as many rows as groups he was affiliated. 
2. Date columns, for each grade item, shows the last modification date for this item grade


Install
=======

After download as a ZIP file, rename the plugin directory to "xlsgd" and transfer into moodle\grade\export\. 
Check admin notifications to install.


Changes for Moodle 3.2
======================

This is a patched version of the plugin, based on the last known release. It used deprecated code (changed by MDL-46548, introduced in Moodle 2.8) but it works in Moodle 3.2.

If you want to test this code in other versions of Moodle (between 2.8 and 3.1), edit the file version.php, and change $plugin->version  = 2013092101; with the right value of the Moodle version you need. The values are listed here https://docs.moodle.org/dev/Releases#Moodle_3.2

If you have success using this plugin in other Moodle versions, send an Issue in Github and I will update the minimum version :D


Original maintainer
===================

Carina Martinez
https://moodle.org/user/profile.php?id=298052


Patches for Moodle 3.2
======================

Juan Eladio Sanchez Rosas
https://moodle.org/user/profile.php?id=2121374


License
=======

Released Under the GNU General Public Licence http://www.gnu.org/copyleft/gpl.html
