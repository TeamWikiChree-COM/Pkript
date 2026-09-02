# Pkript/設定/スクリプトの配置

スクリプトの置き場所と呼び出し口に関する設定定数。

## リファレンス

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_SCRIPT_DIR | string | 'plugin/pkript/script/' | ファイルスクリプトの配置ディレクトリ |
| PKRIPT_SCRIPT_EXT | string | 'pks,js' | 受け付ける拡張子（カンマ区切り、左が優先） |
| PKRIPT_ALLOW_PAGE_SCRIPT | int | 1 | ページスクリプト（:config/pkript/script/）を有効にするか |
| PKRIPT_PAGE_SCRIPT_FROZEN_ONLY | int | 0 | ページスクリプトと #pks を凍結ページのみに限定するか |
| PKRIPT_PAGE_PREFIX | string | ':config/pkript/script/' | ページスクリプトのページ名接頭辞 |
| PKRIPT_BIND | int | 1 | スクリプト名による直接呼び出し（#name 形式）を有効にするか |

PKRIPT_BIND を 0 にすると #pkript(name) と &amp;pkript(name); のみが有効な呼び出し口になる（[Pkript/設定/直接呼び出し](Pkript_%E8%A8%AD%E5%AE%9A_%E7%9B%B4%E6%8E%A5%E5%91%BC%E3%81%B3%E5%87%BA%E3%81%97.md) 参照）。

PKRIPT_PAGE_SCRIPT_FROZEN_ONLY は #pks にも効く。#pks の信頼度もページスクリプトと同じ規則で決まる（[PkriptRunner](PkriptRunner.md) 参照）。
