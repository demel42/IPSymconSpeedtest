<?php

declare(strict_types=1);

trait SpeedtestLocalLib
{
    public static $IS_INVALIDPREREQUISITES = IS_EBASE + 1;
    public static $IS_SERVICEFAILURE = IS_EBASE + 2;
    public static $IS_UNKNOWNSERVER = IS_EBASE + 3;

    private function GetFormStatus()
    {
        $formStatus = [];

        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => self::$IS_INVALIDPREREQUISITES, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid preconditions)'];
        $formStatus[] = ['code' => self::$IS_SERVICEFAILURE, 'icon' => 'error', 'caption' => 'Instance is inactive (service failure)'];
        $formStatus[] = ['code' => self::$IS_UNKNOWNSERVER, 'icon' => 'error', 'caption' => 'Instance is inactive (unknown server)'];

        return $formStatus;
    }
}
