# Pkript/設定/信頼度

スクリプトの配置場所で決まる信頼度と、書き込みと import の可否。

## リファレンス

スクリプトの配置場所によって信頼度が決まる。

| 配置場所 | 定数 | wiki.write の可否 |
| --- | --- | --- |
| plugin/pkript/script/（ファイル） | PKRIPT_TRUST_FILE | 可 |
| :config/pkript/script/（凍結ページ） | PKRIPT_TRUST_FROZEN | 不可 |
| :config/pkript/script/（編集可能ページ） | PKRIPT_TRUST_PAGE | 不可 |

読み出しと表示、計算はすべての信頼度で共通。書き込みのみ信頼度で制限される。

### import と信頼度

import しても信頼度は昇格しない。

- 実行全体の信頼度は、関わったすべてのスクリプトの最低値になる。凍結ページがファイルのライブラリを読み込んでも、wiki.write の判定は凍結ページの信頼度で行われる
- 自スクリプトより低い信頼度のスクリプトは読み込めない。PKRIPT_IMPORT_LOWER_TRUST を 1 にすると許可されるが、実行全体の信頼度は低い方に落ちる

### 権限まわりの設定定数

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_WRITE_MIN_TRUST | int | PKRIPT_TRUST_FILE | ページを書き込める最低の信頼度 |
| PKRIPT_IMPORT_LOWER_TRUST | int | 0 | 自スクリプトより低い信頼度のスクリプトの import を許可するか |
| PKRIPT_SECRET_FILE | string | CACHE_DIR . 'pkript_secret.dat' | CSRFトークン秘密鍵の保存場所 |
