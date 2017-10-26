<?php
set_time_limit (0);

$address = '0.0.0.0';

$port = 7777;
$con = 1;
$word = "";

$sock = socket_create(AF_INET, SOCK_STREAM, 0);
$bind = socket_bind($sock, $address, $port);

socket_listen($sock);

while ($con == 1)
{
    $client = socket_accept($sock);
    $input = socket_read($client, 2024);

    if ($input == 'exit') 
    {
        $close = socket_close($sock);
        $con = 0;
    }

    if($con == 1)
    {
        $word .= $input;
    }
}

echo $word;
