# Tresipunt Assign Submission NEXT CLOUD #

Assign Submission with Next Cloud

## Instalation

Copy project inside Moodle folder

```
/mod/assign/submission/tipnc/
```

## Configuration

/admin/settings.php?section=assignsubmission_tipnc

Example:
* Host NextCloud: https://nextcloud.xxxxxx.net
* User NextCloud: moodle
* Password: ******
* Folder name: tasks
  *(Name of the folder where the tasks are in NextCloud)*
* Template name: template.docx
  *(Name of the template to be used for NextCloud tasks)*
* Location URL: /apps/onlyoffice/
  *(Part the Nextcloud URL to view the document. Ex: https://nextcloud.xxxx.net/apps/onlyoffice/3769)*

## Error Logs Page

Page to see errors in the synchronization of assign and NextCloud activities

/mod/assign/submission/tipnc/view_errors.php

## License ##

2021 Tresipunt <contacte@tresipunt.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
