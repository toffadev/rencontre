<?php

$app_id = rand(100000, 999999);
$app_key = bin2hex(random_bytes(16));
$app_secret = bin2hex(random_bytes(32));

echo "REVERB_APP_ID=$app_id\n";
echo "REVERB_APP_KEY=$app_key\n";
echo "REVERB_APP_SECRET=$app_secret\n";
