<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Car Reserve Window
    |--------------------------------------------------------------------------
    |
    | If a reservation is pending/confirmed and starts within this number of
    | hours, the related car is set to "reserved". Outside this window, the car
    | can remain "available" to avoid locking inventory too early.
    |
    */
    'reserve_before_hours' => (int) env('RENTAL_RESERVE_BEFORE_HOURS', 24),
];

