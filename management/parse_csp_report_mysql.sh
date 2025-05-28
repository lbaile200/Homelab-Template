# Configuration
DB_NAME="**censored**"
DB_USER="**censored**"
DB_PASS="**censored**"
DB_HOST="**censored**"
DB_PORT="**censored**"

INPUT_FILE="$1"

if [ -z "$INPUT_FILE" ]; then
    echo "Usage: $0 path_to_log_file.json"
    exit 1
fi

while read -r line; do
    log_date=$(echo "$line" | jq -r '.date' | sed 's#/#-#g' | sed 's/ -0400//')
    ip_address=$(echo "$line" | jq -r '."IP address"')
    forwarded_for=$(echo "$line" | jq -r '.http_x_forwarded_for')
    status=$(echo "$line" | jq -r '.status')
    user_agent=$(echo "$line" | jq -r '.http_user_agent')
    body_bytes_sent=$(echo "$line" | jq -r '.body_bytes_sent')
    request=$(echo "$line" | jq -r '.request')
    request_body=$(echo "$line" | jq -r '.request_body')

    blocked_uri=$(echo "$request_body" | jq -r '.["csp-report"]["blocked-uri"] // empty')
    violated_directive=$(echo "$request_body" | jq -r '.["csp-report"]["violated-directive"] // empty')
    original_policy=$(echo "$request_body" | jq -r '.["csp-report"]["original-policy"] // empty')
    document_uri=$(echo "$request_body" | jq -r '.["csp-report"]["document-uri"] // empty')
    source_file=$(echo "$request_body" | jq -r '.["csp-report"]["source-file"] // empty')
    line_number=$(echo "$request_body" | jq -r '.["csp-report"]["line-number"] // 0')
    column_number=$(echo "$request_body" | jq -r '.["csp-report"]["column-number"] // 0')
    script_sample=$(echo "$request_body" | jq -r '.["csp-report"]["script-sample"] // empty')

    # Escape single quotes for SQL
    user_agent_esc=$(printf "%s" "$user_agent" | sed "s/'/''/g")
    request_esc=$(printf "%s" "$request" | sed "s/'/''/g")
    blocked_uri_esc=$(printf "%s" "$blocked_uri" | sed "s/'/''/g")
    violated_directive_esc=$(printf "%s" "$violated_directive" | sed "s/'/''/g")
    original_policy_esc=$(printf "%s" "$original_policy" | sed "s/'/''/g")
    document_uri_esc=$(printf "%s" "$document_uri" | sed "s/'/''/g")
    source_file_esc=$(printf "%s" "$source_file" | sed "s/'/''/g")
    script_sample_esc=$(printf "%s" "$script_sample" | sed "s/'/''/g")

    # Insert into MySQL
    mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -P"$DB_PORT" "$DB_NAME" -e "
        INSERT INTO csp_reports (
            log_date, ip_address, forwarded_for, status, user_agent,
            body_bytes_sent, request, blocked_uri, violated_directive,
            original_policy, document_uri, source_file,
            line_number, column_number, script_sample
        ) VALUES (
            STR_TO_DATE('$log_date', '%d-%b-%Y:%H:%i:%s'),
            '$ip_address',
            '$forwarded_for',
            $status,
            '$user_agent_esc',
            $body_bytes_sent,
            '$request_esc',
            '$blocked_uri_esc',
            '$violated_directive_esc',
            '$original_policy_esc',
            '$document_uri_esc',
            '$source_file_esc',
            $line_number,
            $column_number,
            '$script_sample_esc'
        );
    "

done < "$INPUT_FILE"

> $INPUT_FILE
