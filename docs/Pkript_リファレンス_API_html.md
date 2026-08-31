#author("2026-08-31T21:40:00+09:00","","")

# Pkript/リファレンス/API/html

HTMLエスケープ・変換のユーティリティ。

## リファレンス

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| html.escape(str) | String | &amp; &lt; &gt; " ' を HTML エンティティに変換（htmlsc() と同等） |
| html.br(str) | String | 改行文字（\n）を &lt;br /&gt; に変換 |
| html.strip(str) | String | HTMLタグを除去 |
