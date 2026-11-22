<<<<<<< HEAD
#!/usr/bin/env bash

basedir=$( dirname $( readlink -f ${BASH_SOURCE[0]} ) )

=======
#!/usr/bin/env bash

basedir=$( dirname $( readlink -f ${BASH_SOURCE[0]} ) )

>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
php -dphar.readonly=0 "$basedir/other/build_phar.php" $*