#!/bin/bash
# To run this file, add this line to your crontab:
# * * * * * /path/to/scheduler.sh > /dev/null 2>&1

i=0

while [ $i -lt 59 ]; do # 59 one-second intervals in 1 minute
  # Get the directory of the current script
  SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

  # Use the script directory to find scheduler.php
  php "$SCRIPT_DIR/scheduler.php" > /dev/null 2>&1 &
  sleep 1
  i=$(( i + 1 ))
done
