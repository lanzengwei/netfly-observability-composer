<?php

declare(strict_types=1);

return [
    ['GET', '/', 'DemoController@index'],
    ['POST', '/orders', 'OrderController@create'],
    ['GET', '/orders', 'OrderController@show'],
    ['GET', '/orders/{id}', 'OrderController@show'],
    ['POST', '/payments/callback', 'PaymentController@callback'],
    ['POST', '/inventory/reserve', 'InventoryController@reserve'],
    ['GET', '/demo/traffic', 'DemoController@traffic'],
    ['GET', '/demo/slow', 'DemoController@slow'],
    ['GET', '/demo/error', 'DemoController@error'],
    ['GET', '/metrics', 'DemoController@metrics'],
];
