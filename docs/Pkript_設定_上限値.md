# Pkript/設定/上限値

実行を打ち切る上限値の設定定数。上限に達したエラーは try / catch で捕捉できない（[Pkript/文法/エラー処理](Pkript_%E6%96%87%E6%B3%95_%E3%82%A8%E3%83%A9%E3%83%BC%E5%87%A6%E7%90%86.md) 参照）。

## リファレンス

### 正規表現の制限

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_REGEX_BACKTRACK | int | 100000 | 1回の照合で許すバックトラック量（ReDoS対策） |
| PKRIPT_MAX_REGEX | int | 512 | パターンの最大バイト数 |

### リクエスト単位の上限

1ページに #pkript を複数書いても、wiki.convert の入れ子でスクリプトが動いても、以下は1リクエストの合計値として適用される。

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_MAX_STEPS | int | 1000000 | 評価ステップ数 |
| PKRIPT_MAX_TIME | float | 3 | Pkript内での実行時間（秒） |
| PKRIPT_MAX_MEMORY | int | memory_limit の 3/4 | メモリ使用量（バイト） |
| PKRIPT_MAX_CONVERT | int | 32 | wiki.convert() の呼び出し回数 |
| PKRIPT_MAX_READS | int | 5000 | ページ参照回数（wiki.exists / source / pages / link の合計） |
| PKRIPT_MAX_WRITES | int | 4 | ページ書き込み回数（wiki.write / append の合計） |

### 値単位の上限

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_MAX_DEPTH | int | 64 | 関数の最大呼び出し深度 |
| PKRIPT_MAX_LOOP | int | 100000 | 1ループの最大繰り返し回数 |
| PKRIPT_MAX_STRING | int | 1048576 | 文字列の最大バイト数（1MB） |
| PKRIPT_MAX_ARRAY | int | 10000 | 配列の最大要素数 |
| PKRIPT_MAX_PAGES | int | 1000 | wiki.pages() が返せる最大ページ数 |
| PKRIPT_MAX_PAGE_BYTES | int | 524288 | wiki.write() で書けるページの最大バイト数（512KB） |
| PKRIPT_MAX_IMPORTS | int | 16 | import できる最大スクリプト数 |
| PKRIPT_MAX_IMPORT_DEPTH | int | 4 | import の最大深度 |
