<?php

class PhoneProperties {
    const FKEYS_NO = array(
        "" => 1,
        "snomD335" => 32,
        "snomD385" => 48,
        "snomD735" => 32,
        "snomD785" => 24,
        "snomD862" => 32,
        "snomD865" => 40,
    );

    const HAS_SMART_LABEL = array(
        "snomD335" => false,
        "snomD385" => false,
        "snomD735" => true,
        "snomD785" => true,
        "snomD862" => false,
        "snomD865" => false,
    );
    
    public static function getFkeysRange(string $phoneType): array {
        return range(1, self::FKEYS_NO[$phoneType]);
    }

    public static function hasSmartLabel(string $phoneType): bool {
        return self::HAS_SMART_LABEL[$phoneType];
    } 
} 