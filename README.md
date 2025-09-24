# Laravel Stup Image
Stup Image is a Package For Storing / Updating the Images, More Clear Codes and Upgrade Readability. Integrated with Intervention Image Library

## Installation Guide

1. Install the package via Composer:
```bash
composer require daycode/stup-images
```

2. Dump and optimize the autoloader:
```bash
composer dump-autoload && php artisan optimize:clear
```

3. Link the storage directory:
```bash
php artisan storage:link
```

## Usage Examples
Use the `Stupable` trait in your controller or any class to handle image upload and storage.

```php
use Daycode\StupImage\Stupable;
```

### Store to Database Example
```php
public function store(Request $request): RedirectResponse
{
    User::create(... + [
        'thumbnail' => $this->uploadFile($request->file('thumbnail'), 'images/thumbnail'),
    ]);

    return redirect()->back()->with('success', 'User Successfully Created');
}
```

### Update to Database Example
```php
public function update(Request $request, $id): RedirectResponse
{
    $user = User::findOrFail($id);
    $user->update(... + [
        'thumbnail' => !empty($request->thumbnail)
            ? $this->syncUploadFile($request->file('thumbnail'), $user->thumbnail, 'images/thumbnail')
            : $user->thumbnail,
    ]);

    return redirect()->back()->with('success', 'User Successfully Updated');
}
```

## Credits
- [Intervention](https://github.com/Intervention)
- [Intervention Image](https://github.com/Intervention/image)
- [Wirandra Alaya](https://github.com/dayCod)






