# Pkript/設定/CSRFトークン

wiki.write / wiki.append が要求するトークンの仕様。

## リファレンス

wiki.write / wiki.append は wiki.token() が返すトークンを要求する。トークンはログイン中のユーザーに紐付く。

認証を設定していないWikiではトークンはサイト共通の値になる（Wikiを読めるユーザーはトークンも読める）。誰でも編集できるWikiで書き込みを制限するには $edit_auth_pages を使うこと。
