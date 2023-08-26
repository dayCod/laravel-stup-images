# Laravel Stup Images
Stup Images or Store Or Update Images is a Package For Storing / Updating the Images, More Clear Codes and Upgrade Readability.

## Installation Guide
```bash
composer require daycode/stup-images
```
Update to the newest laravel project vendor
```bash
composer update
```

Last, for make sure this package installed correctly.
```bash
composer dump-autoload && php artisan optimize:clear
```

Because this package use storage path as a default path, You must to link between public and storage folder:
```bash
php artisan storage:link
```

## Usage Explanation
You must use these class on top of your codes
```php
use DayCod\StupImages\StupImages;
use DayCod\StupImages\Functions\StupImageFunctions;
```

and then for the usage let's use this functions
```php
(new StupImages($filename, $path, $width, $height))->StupImagesToStorage($new_image_file, $old_image_file = null)
```
Here's the rules that you must to follow:
```php
$filename = "Filename cannot be an empty string ``";
$path = "Path cannot be an empty string ``";

$width = "Width input must be an integer";
$width = "Width Basically is Dimension and Dimension would`nt be lower than zero or negative";

$height = "Height input must be an integer";
$height = "Height Basically is Dimension and Dimension would`nt be lower than zero or negative";
```

if you want to get the filename use this:
```php
(new StupImageFunctions())->getFileName($old_image_file);

// $old_image_file is a value from the database table where you stored at
```

## Usage Examples

### Store to Database Example

It's Absolutely Good, But we can improve.
```php
public function store(Request $request): RedirectResponse
{
    $data = [
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
    ];

    $image = $request->file('images');
    $imageName = hexdec(uniqid()).'.'.$image->getClientOriginalExtension();
    $dir = 'upload/images/';
    $save_path = 'upload/images/'.$imageName;

    Image::make($realImage)->resize($width, $height)->save(public_path($save_path));

    $data['user_profile_img'] = $save_path;

    User::create($data);

    return redirect()->back()->with('success', 'User Successfully Created');
}
```

To this,
```php
public function store(Request $request): RedirectResponse
{
    User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'user_profile_img' => (new StupImages('user-profile', 'profile', 300, 300))->StupImagesToStorage($request->file('user_profile_img')),
    ]);

    return redirect()->back()->with('success', 'User Successfully Created');
}
```

### Update to Database Example

It's Absolutely Good, But we can improve.
```php
public function update(Request $request, $id): RedirectResponse
{
    $data = [
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
    ];

    $user = User::findOrFail($id);

    if ($request->file('images')) {
        $image = $request->file('images');
        $imageName = hexdec(uniqid()).'.'.$image->getClientOriginalExtension();
        $dir = 'upload/images/';
        $save_path = 'upload/images/'.$imageName;
    
        Image::make($realImage)->resize($width, $height)->save(public_path($save_path));
    
        $data['images'] = $save_path;
    
        $user->update($data);
    } else { $user->update($data); }

    return redirect()->back()->with('success', 'User Successfully Updated');
}
```

To this
```php
public function update(Request $request, $id): RedirectResponse
{
    $user = User::find($user_id);
    $user->update([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'user_profile_img' => (is_null($request->file('user_profile_img'))) 
            ? $user->fresh()->user_profile_img 
            : (new StupImages('user-profile', 'profile', 300, 300))
                ->StupImagesToStorage($request->file('user_profile_img'), $user->fresh()->user_profile_img),
    ]);
}
```

## Credits
- [Intervention](https://github.com/Intervention)
- [Intervention Image](https://github.com/Intervention/image)
- [Wirandra Alaya](https://github.com/dayCod)

## Version
| Release                | Supported Versions |
|------------------------|--------------------|
| Laravel Stup Img 1.0.0 | Laravel 8/9/10     |
| Intervention Images    | ^2.7               |





