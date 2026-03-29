#!/usr/bin/env bash

disk="/dev/nvme0n1p1"  # replace with the disk that you are monitoring

percentage=$(df -hl --total ${disk} | tail -1 | awk '{printf $5}')

threshold="80"  # 80% seems reasonable, but YMMV

number=${percentage%\%*}

message="Used space on ${disk} is ${number}%" 

push_url="https://uptime.homelabcabbage.synology.me/api/push/ciTwRmMFt3"

if [ $number -lt $threshold ]; then
    service_status="up"
else
    service_status="down"
fi

curl \
    --get \
    --data-urlencode "status=${service_status}" \
    --data-urlencode "msg=${message}" \
    --data-urlencode "ping=${number}" \
    --silent \
    ${push_url} \
    > /dev/null
