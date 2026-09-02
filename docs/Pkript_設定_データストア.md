# Pkript/設定/データストア

data.* によるデータ保存に関する設定定数。

## リファレンス

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_ALLOW_DATA | int | 1 | data.* によるデータ保存を有効にするか |
| PKRIPT_DATA_PREFIX | string | ':config/pkript/data/' | データページの接頭辞 |
| PKRIPT_DATA_MIN_TRUST | int | PKRIPT_WRITE_MIN_TRUST | data.set() に必要な最低信頼度 |

PKRIPT_ALLOW_DATA を 0 にすると data.get() は常に既定値を返し、data.set() は拒否される。
