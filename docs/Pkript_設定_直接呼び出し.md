# Pkript/設定/直接呼び出し

#pkript(name) の代わりに #name と書いて呼び出す仕組み（PKRIPT_BIND）。

## リファレンス

PKRIPT_BIND が 1（既定）のとき、#pkript(hello) の代わりに #hello と書いて呼び出せる。

lib/plugin.php の exist_plugin が実在するPHPプラグインを探して見つからなかったときだけ、同名のスクリプトを探してラッパー関数を生成する。同名のPHPプラグインは常に優先される。#edit や #attach をスクリプトで置き換えることはできない。

```text
#hello(World)
&hello(World);
```

PKRIPT_BIND を 0 にするとラッパー生成が無効になる。

```php
// pukiwiki.ini.php
define('PKRIPT_BIND', 0);   // 既定は 1
```

exist_plugin のフォールバック処理を独自改造している環境では、exist_plugin 関数に以下を追加する。

```php
if (file_exists(PLUGIN_DIR . 'pkript.inc.php')) {
    require_once(PLUGIN_DIR . 'pkript.inc.php');
    if (function_exists('plugin_pkript_bind') && plugin_pkript_bind($name)) {
        $exist[$name] = TRUE;
        $count[$name] = 1;
        return TRUE;
    }
}
```
