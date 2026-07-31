<?php
echo "Importing configuration from yml files...\n";
passthru('drush config:import -y');
echo "Rebuilding cache...\n";
passthru('drush cache:rebuild');
