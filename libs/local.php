<?php

declare(strict_types=1);

trait SpeedtestLocalLib
{
    public static $IS_SERVICEFAILURE = IS_EBASE + 10;
    public static $IS_UNKNOWNSERVER = IS_EBASE + 11;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_SERVICEFAILURE, 'icon' => 'error', 'caption' => 'Instance is inactive (service failure)'];
        $formStatus[] = ['code' => self::$IS_UNKNOWNSERVER, 'icon' => 'error', 'caption' => 'Instance is inactive (unknown server)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_SERVICEFAILURE:
            case self::$IS_UNKNOWNSERVER:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    public static $MODE_SPEEDTEST_CLI = 0;
    public static $MODE_OOKLA = 1;

    public function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $this->CreateVarProfile('Speedtest.ms', VARIABLETYPE_FLOAT, ' ms', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('Speedtest.MBits', VARIABLETYPE_FLOAT, ' MBit/s', 0, 0, 0, 1, '', [], $reInstall);
    }
}
