<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Hash Filename
    |--------------------------------------------------------------------------
    |
    | This option determines whether uploaded filenames should be hashed using
    | MD5 with timestamp. When enabled, original filenames will be converted
    | to a secure hash format to prevent naming conflicts and enhance security.
    |
    | Default: true
    |
    */
    'hash_filename' => true,

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | Specify which file extensions are allowed for upload. Use ['*'] to allow
    | all file types, or define specific extensions like ['jpg', 'png', 'gif'].
    | This helps control what types of files can be uploaded to your storage.
    |
    | Default: ['*'] (all extensions allowed)
    |
    */
    'allowed_extensions' => ['*'],

];