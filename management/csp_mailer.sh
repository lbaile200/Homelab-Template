#!/bin/bash

#Variables
DATE_TODAY=$(date +"%d/%b/%Y")
DATE_YESTERDAY=$(date +"%d/%b/%Y" -d "yesterday")
LOG_DATE_YESTERDAY=$(date +"%Y%m%d")
#These are necessary for sed to pass command properly, there is probably a better way, I don't know it.
ESCAPED_DATE_TODAY=$(echo "$DATE_TODAY" | sed 's/\//\\\//g')
ESCAPED_DATE_YESTERDAY=$(echo "$DATE_YESTERDAY" | sed 's/\//\\\//g')

# Set file paths
SOURCE_FILE="/home/sys_user/bin/csp_parsed.txt"
DEST_FILE="/var/www/html/management/csp_parsed.txt"
LOG_FILE_BASE="/var/log/nginx/csp.log"
## for working with logrotated files.  Can switch to use active CSP from post logrotate
#LOG_FILE="${LOG_FILE_BASE}-${LOG_DATE_YESTERDAY}"
LOG_FILE="$LOG_FILE_BASE"

# Email settings
EMAIL_SUBJECT="CSP REPORT FOR $DATE_YESTERDAY"
EMAIL_TO="lbaile200@gmail.com"
EMAIL_FROM="lucasbailey@lucasbailey.net"

# Options for getting the CSP report
## Sed, portable, works on any machine.
SEDVAR=$(sed -n "/$ESCAPED_DATE_YESTERDAY/,\$p" "$LOG_FILE")
## jq, requires jq installed to work, makes prettier reports.
CSP_PARSED=$(jq -r '
 
  .date as $date |
  ."IP address" as $ip |
  .status as $status |
  .http_user_agent as $user_agent |
  (.request_body | fromjson | ."csp-report") as $csp |
 [
  "Date: \($date)",
  "IP Address: \($ip)",
  "Status: \($status)",
  "User Agent: \($user_agent)",
  "Violated Directive: \($csp."violated-directive")",
  "Effective Directive: \($csp."effective-directive")",
  "Blocked URI: \($csp."blocked-uri")",
  "Line Number: \($csp."line-number")",
  "Source File: \($csp."source-file")"
 ] | join ("<br>")
' $LOG_FILE)

#debugging
#EMAIL_TO="lbailey200@gmail.com"
#echo "$ESCAPED_DATE_YESTERDAY"
#echo "$ESCAPED_DATE_TODAY"
#echo "$DATE_TODAY"
#echo "$DATE_YESTERDAY"
#echo "$LOG_FILE"
#echo "$SEDVAR"
#echo "sed -n "/$ESCAPED_DATE_YESTERDAY/,\$p" "$LOG_FILE""
#echo "$CSP_PARSED"



# Check if the source file is not empty
if [ -s "$LOG_FILE" ]; then
  {
    #echo "To: $EMAIL_TO"
    #echo "From: $EMAIL_FROM"
    #echo "Subject: $EMAIL_SUBJECT"
    #echo "Content-Type: text/html; charset=UTF-8"
    #echo ""
##Use either SEDVAR or CSP_PARSED here depending on your environment and what it supports.  Using both is ugly and repetetive. 
#    echo $SEDVAR
    #echo "<html><body>"
    #echo $CSP_PARSED > $DEST_FILE 
    #echo "</body></html"
	jq -c . $LOG_FILE > $DEST_FILE
  } 

> $LOG_FILE
 
else
  echo "The source file is empty. No email sent."
fi
