<?php

class DeviceProperties
{
    const EXPANSION_MODULES = ["D7C", "D7", "D3"];
    const FKEYS_NO = array(
        "" => 1,
        "snomD335" => 32,
        "snomD385" => 48,
        "snomD735" => 32,
        "snomD785" => 24,
        "snomD862" => 32,
        "snomD865" => 40,
        "snomD7C" => 48,
        "snomD7" => 18,
        "snomD3" => 18,
    );

    const HAS_SMART_LABEL = array(
        "snomD335" => false,
        "snomD385" => false,
        "snomD735" => true,
        "snomD785" => true,
        "snomD862" => false,
        "snomD865" => false,
        "snomD7C" => false,
        "snomD7" => false,
        "snomD3" => false,
    );

    const FKEY_LED_OFFSET = array(
        "snomD335" => 2,
        "snomD385" => 2,
        "snomD735" => 5,
        "snomD785" => 5,
        "snomD862" => 5,
        "snomD865" => 5,
    );

    public static function getFkeysRange(string $phoneType, string $connectedExpansionModule): array
    {
        $expansionFkeyRange = [];

        if ($connectedExpansionModule) {
            if ($phoneType === "snomD385") {
                $start = self::FKEYS_NO[$phoneType] + 127;
                $end = self::FKEYS_NO[$phoneType] + 126 + self::FKEYS_NO[$connectedExpansionModule];
            } else {
                $start = self::FKEYS_NO[$phoneType] + 1;
                $end = self::FKEYS_NO[$phoneType] + self::FKEYS_NO[$connectedExpansionModule];
            }
            $expansionFkeyRange = range($start, $end);
        }

        $phoneFkeyRange = range(1, self::FKEYS_NO[$phoneType]);

        return array_merge($phoneFkeyRange, $expansionFkeyRange);
    }

    public static function hasSmartLabel(string $phoneType): bool
    {
        return self::HAS_SMART_LABEL[$phoneType];
    }

    public static function getFkeyLedNo(string $phoneType, int $fkeyNo): int
    {
        return $fkeyNo + self::FKEY_LED_OFFSET[$phoneType];
    }
}