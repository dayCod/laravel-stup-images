# Charable Laravel Facade
Stup Images or Store Or Update Images is a Package For Storing / Updating the Images, More Clear Codes and Upgrade Readability.

## Installation Guide
```bash
composer require daycode/charable
```
Update to the newest laravel project vendor
```bash
composer update
```

Last, for make sure this package installed correctly.
```bash
composer dump-autoload && php artisan optimize:clear
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
Here's the rules that you have to follow:
```php
$filename = "Filename cannot be an empty string ``";
$path = "Path cannot be an empty string ``";
$width = "Width Basically is Dimension and Dimension would`nt be lower than zero or negative";
$height = "Height Basically is Dimension and Dimension would`nt be lower than zero or negative";
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
        'user_profile_img' => (is_null($request->file('user_profile_img'))) ? $user->fresh()->user_profile_img : (new StupImages('user-profile', 'profile', 300, 300))->StupImagesToStorage($request->file('user_profile_img'), $user->user_profile_img),
    ]);
}
```

## Tested on 
| Release                | Supported Versions |
|------------------------|--------------------|
| Laravel Charable 1.0.0 | Laravel 8/9/10     |





