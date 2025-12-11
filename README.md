# Laravel Stup Image

**Stup Image** is a powerful Laravel package for storing and updating images with clean, readable code. Integrated with the Intervention Image library, it provides an elegant solution for handling file uploads with advanced features like automatic resizing, configurable filename hashing, and flexible storage options.

[![Downloads](https://img.shields.io/packagist/dt/daycode/stup-images)](https://packagist.org/packages/daycode/stup-images)
[![License](https://img.shields.io/packagist/l/daycode/stup-images)](https://packagist.org/packages/daycode/stup-images)

---

## ✨ Features

- 🚀 Simple and intuitive API for file uploads
- 🖼️ Automatic image resizing on upload
- 🔒 Configurable filename hashing for security
- 📁 Flexible storage disk configuration (local, S3, etc.)
- 🔄 Sync upload (replace old file automatically)
- 📦 Multiple file uploads support
- 🗑️ Easy file deletion
- ⚙️ Customizable configuration
- ✅ File extension validation

---

## 📦 Installation

### 1. Install via Composer

```bash
composer require daycode/stup-images
```

### 2. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=stup-image
```

This will create a `config/stup-image.php` file where you can customize the package behavior.

### 3. Configure Storage Disk

Make sure your `config/filesystems.php` has the desired disk configured. By default, the package uses the `FILESYSTEM_DISK` from your `.env` file.

```env
FILESYSTEM_DISK=public
```

### 4. Link Storage (if using public disk)

```bash
php artisan storage:link
```

### 5. Clear Cache

```bash
php artisan optimize:clear
```

---

## ⚙️ Configuration

After publishing the config file, you can customize these options in `config/stup-image.php`:

```php
return [
    // Hash filenames using MD5 + timestamp for security
    'hash_filename' => true,

    // Allowed file extensions. Use ['*'] for all, or specify: ['jpg', 'png', 'gif']
    'allowed_extensions' => ['*'],
];
```

---

## 🚀 Usage

### Basic Setup

Add the `Stupable` trait to your controller or any class:

```php
use Daycode\StupImage\Stupable;

class UserController extends Controller
{
    use Stupable;
    
    // Your methods here...
}
```

---

### 📤 Upload File

Upload a single file to storage:

```php
public function store(Request $request)
{
    $filename = $this->uploadFile(
        file: $request->file('avatar'),
        path: 'images/avatars'
    );

    User::create([
        'name' => $request->name,
        'avatar' => $filename,
    ]);

    return redirect()->back()->with('success', 'User created successfully!');
}
```

---

### 🖼️ Upload with Auto Resize

Automatically resize images during upload:

```php
public function store(Request $request)
{
    // Resize to 800x600 pixels
    $filename = $this->uploadFile(
        file: $request->file('thumbnail'),
        path: 'images/thumbnails',
        resize: [800, 600]
    );

    Post::create([
        'title' => $request->title,
        'thumbnail' => $filename,
    ]);
}
```

---

### 🔄 Sync Upload (Update & Replace)

Upload a new file and automatically delete the old one:

```php
public function update(Request $request, User $user)
{
    $filename = $user->avatar;

    if ($request->hasFile('avatar')) {
        $filename = $this->syncUploadFile(
            file: $request->file('avatar'),
            oldFileName: $user->avatar,
            path: 'images/avatars'
        );
    }

    $user->update([
        'name' => $request->name,
        'avatar' => $filename,
    ]);

    return redirect()->back()->with('success', 'User updated successfully!');
}
```

---

### 📦 Upload Multiple Files

Upload multiple files at once:

```php
public function store(Request $request)
{
    $filenames = $this->uploadMultipleFiles(
        files: $request->file('gallery'),
        path: 'images/gallery'
    );

    foreach ($filenames as $filename) {
        Gallery::create([
            'image' => $filename,
        ]);
    }

    return redirect()->back()->with('success', 'Gallery uploaded successfully!');
}
```

---

### 🗑️ Delete File

Delete a file from storage:

```php
public function destroy(User $user)
{
    $this->deleteFile(
        fileName: $user->avatar,
        path: 'images/avatars'
    );

    $user->delete();

    return redirect()->back()->with('success', 'User deleted successfully!');
}
```

---

## 📖 API Reference

### `uploadFile(UploadedFile $file, string $path, ?array $resize = []): string`

Upload a single file to storage.

**Parameters:**
- `$file` - The uploaded file instance
- `$path` - Storage path (relative to your configured disk)
- `$resize` - Optional. Array with `[width, height]` for automatic resizing

**Returns:** Filename (string) or throws `UploadException`

---

### `syncUploadFile(UploadedFile $file, ?string $oldFileName, string $path): string`

Upload a new file and delete the old one.

**Parameters:**
- `$file` - The new uploaded file
- `$oldFileName` - The old filename to delete (nullable)
- `$path` - Storage path
- `$resize` - Optional. Array with `[width, height]` for automatic resizing

**Returns:** New filename (string)

---

### `uploadMultipleFiles(array $files, string $path): array`

Upload multiple files at once.

**Parameters:**
- `$files` - Array of uploaded files
- `$path` - Storage path
- `$resize` - Optional. Array with `[width, height]` for automatic resizing

**Returns:** Array of filenames or throws `UploadException`

---

### `deleteFile(string $fileName, string $path): void`

Delete a file from storage.

**Parameters:**
- `$fileName` - The filename to delete
- `$path` - Storage path

---

## 🔧 Advanced Configuration

### Using Different Storage Disks

The package respects your `FILESYSTEM_DISK` environment variable. To use different disks:

**Local Storage:**
```env
FILESYSTEM_DISK=public
```

**Amazon S3:**
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

**DigitalOcean Spaces, Cloudinary, etc.:**
Configure your disk in `config/filesystems.php` and set `FILESYSTEM_DISK` accordingly.

---

### Restrict File Extensions

Edit `config/stup-image.php`:

```php
'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
```

---

### Disable Filename Hashing

If you want to keep original filenames:

```php
'hash_filename' => false,
```

---

## 🛡️ Security

- Filenames are hashed by default using MD5 + timestamp to prevent conflicts and enhance security
- File extension validation prevents unauthorized file types
- Uses Laravel's Storage facade for secure file handling

---

## 📝 Examples

### Complete CRUD Example

```php
use Daycode\StupImage\Stupable;

class ProductController extends Controller
{
    use Stupable;

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'image' => 'required|image|max:2048',
        ]);

        $filename = $this->uploadFile(
            file: $request->file('image'),
            path: 'products',
            resize: [1200, 800]
        );

        Product::create([
            'name' => $request->name,
            'image' => $filename,
        ]);

        return redirect()->route('products.index');
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required',
            'image' => 'nullable|image|max:2048',
        ]);

        $filename = $product->image;

        if ($request->hasFile('image')) {
            $filename = $this->syncUploadFile(
                file: $request->file('image'),
                oldFileName: $product->image,
                path: 'products'
            );
        }

        $product->update([
            'name' => $request->name,
            'image' => $filename,
        ]);

        return redirect()->route('products.index');
    }

    public function destroy(Product $product)
    {
        $this->deleteFile($product->image, 'products');
        $product->delete();

        return redirect()->route('products.index');
    }
}
```

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

## 👨‍💻 Credits

- **Author:** [Wirandra Alaya (dayCod)](https://github.com/dayCod)
- **Intervention Image:** [Intervention](https://github.com/Intervention/image)

---

## 💬 Support

If you find this package helpful, please consider giving it a ⭐ on [GitHub](https://github.com/dayCod/laravel-stup-images) and [Packagist](https://packagist.org/packages/daycode/stup-images)!

For issues or questions, please use the [GitHub Issues](https://github.com/dayCod/laravel-stup-images/issues) page.






