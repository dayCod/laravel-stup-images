# Stup Image documentation

Image uploads for Laravel without tables or migrations.

```php
$image = StupImage::from($request->file('avatar'))->cover(400, 400)->toWebp()->store();
```

- [Installation](installation.md)
- [Configuration](configuration.md)
- [API reference](api.md)
- [Eloquent integration](eloquent.md)
- [Testing](testing.md)
- [Upgrading from v1](../UPGRADE.md)
- [Implementation plan (internal)](IMPLEMENTATION_PLAN.md)
