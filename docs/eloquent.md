# Eloquent integration

The `HasStupImages` trait stores `UploadedFile`s assigned to model attributes and cleans up the images they replace. The image path is stored in a regular nullable string column. No extra tables are involved.

```php
use Daycode\StupImage\Concerns\HasStupImages;

class Product extends Model
{
    use HasStupImages;

    protected array $stupImages = [
        'image' => 'products',  // shorthand for ['directory' => 'products']
        'banner' => [
            'directory' => 'banners',
            'disk' => 's3',
            'preset' => 'medium',
            'variants' => ['thumbnail'],
        ],
    ];
}
```

## Storing and replacing

```php
Product::create(['name' => 'Chair', 'image' => $request->file('image')]);

$product->update(['image' => $request->file('image')]);
```

While the model is being saved, the file is validated, processed and stored, and the attribute becomes the stored path. The previous image, with its variants, is deleted **after** the model has been saved.

If an upload fails, its exception aborts the save and the old image is kept. Images already stored by that save, for other attributes, are removed again.

You can also assign a path string, and it's saved as is.

## Clearing

```php
$product->update(['image' => null]); // the old image is deleted after saving
```

## Deleting models

Deleting the model deletes its images. When the model uses `SoftDeletes`, the images are kept until `forceDelete()`.

If a file can't be deleted, the error is reported through Laravel's exception handler and doesn't fail the request, because the database is already up to date.

## URLs

```php
$product->imageUrl();              // null when empty
$product->bannerUrl('thumbnail');  // URL of a variant
$product->stupImageUrl('image');   // the same as imageUrl()
```

`{attribute}Url()` uses the camel-cased attribute name, so `cover_photo` becomes `coverPhotoUrl()`.

## Notes

- If the database save fails *after* the upload (for example, a constraint violation), the new file stays on the disk.
- Duplicating a model with `replicate()` copies the path, so both models point to the same file. Deleting either of them deletes it.
