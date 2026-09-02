# Pkript/設定/デバッグ

エラー表示、console.log 出力、ASTキャッシュに関する設定定数。

## リファレンス

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_AST_CACHE | int | 1 | 解析済みスクリプトを cache/ に保存するか |
| PKRIPT_DEBUG | int | 1 | エラーに行番号と列番号を付与するか、console.log を出力するか |
| PKRIPT_MAX_LOG | int | 100 | console.log で記録できる行数 |
| PKRIPT_MAX_LOG_BYTES | int | 8192 | console.log 全体の最大バイト数 |
