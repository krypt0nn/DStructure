# Distributed Structure

**DStructure** - распределённая файловая структура для хранения информации

### Установка (Qero)

```
php Qero.phar install KRypt0nn/DStructure
```

### Использование


```php
<?php

namespace DStructure;

$dbs = new Structure (__DIR__ .'/.dbs', 'Encryption key', 'sha256');

$dbs->set ('test', new Item ([
    'example' => 'Hello, World!'
]));

$dbs->save ();

```

Подробности см. в php/Structure.php

Автор: [Подвирный Никита](https://vk.com/technomindlp). Специально для [Enfesto Studio Group](https://vk.com/hphp_convertation)