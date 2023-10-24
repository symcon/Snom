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
    
    public static function getFkeysRange(string $phoneType): array {
        return range(1, self::FKEYS_NO[$phoneType]);
    } 
} 