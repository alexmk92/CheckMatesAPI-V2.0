<?php

namespace Handlers;
use Models\Database;

class App
{
    public static function getDeviceType($deviceType)
    {
        $query = "SELECT name
                  FROM dev_type
                  WHERE dev_id = :deviceId";

        $data  = Array(":deviceId" => $deviceType);
        $res   = Database::getInstance()->fetch($query, $data);

        if (count($res) > 0 && $res)
            $res = Array('flag' => true, 'name' => $res['name']);
    }
}
