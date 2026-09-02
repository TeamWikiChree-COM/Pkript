# Pkript/設定

plugin/pkript.inc.php の設定定数、直接呼び出し、セキュリティの仕様リファレンス。定数を define することで動作を変更できる。pukiwiki.ini.php で define した値が優先される。

- [Pkript/設定/スクリプトの配置](Pkript_%E8%A8%AD%E5%AE%9A_%E3%82%B9%E3%82%AF%E3%83%AA%E3%83%97%E3%83%88%E3%81%AE%E9%85%8D%E7%BD%AE.md): PKRIPT_SCRIPT_DIR, PKRIPT_SCRIPT_EXT, PKRIPT_ALLOW_PAGE_SCRIPT, PKRIPT_PAGE_PREFIX, PKRIPT_BIND
- [Pkript/設定/データストア](Pkript_%E8%A8%AD%E5%AE%9A_%E3%83%87%E3%83%BC%E3%82%BF%E3%82%B9%E3%83%88%E3%82%A2.md): PKRIPT_ALLOW_DATA, PKRIPT_DATA_PREFIX, PKRIPT_DATA_MIN_TRUST
- [Pkript/設定/文法機能](Pkript_%E8%A8%AD%E5%AE%9A_%E6%96%87%E6%B3%95%E6%A9%9F%E8%83%BD.md): PKRIPT_JSX, PKRIPT_REGEX
- [Pkript/設定/上限値](Pkript_%E8%A8%AD%E5%AE%9A_%E4%B8%8A%E9%99%90%E5%80%A4.md): ステップ数、実行時間、メモリ、ページ参照と書き込み、文字列長、import、正規表現バックトラック
- [Pkript/設定/信頼度](Pkript_%E8%A8%AD%E5%AE%9A_%E4%BF%A1%E9%A0%BC%E5%BA%A6.md): 配置場所ごとの信頼度、import と信頼度、PKRIPT_WRITE_MIN_TRUST
- [Pkript/設定/CSRFトークン](Pkript_%E8%A8%AD%E5%AE%9A_CSRF%E3%83%88%E3%83%BC%E3%82%AF%E3%83%B3.md): wiki.token() が返すトークンの仕様
- [Pkript/設定/サニタイザ](Pkript_%E8%A8%AD%E5%AE%9A_%E3%82%B5%E3%83%8B%E3%82%BF%E3%82%A4%E3%82%B6.md): 出力HTMLの自動サニタイズ、style属性で使えるCSS
- [Pkript/設定/デバッグ](Pkript_%E8%A8%AD%E5%AE%9A_%E3%83%87%E3%83%90%E3%83%83%E3%82%B0.md): PKRIPT_DEBUG, PKRIPT_AST_CACHE, console.log の上限
- [Pkript/設定/直接呼び出し](Pkript_%E8%A8%AD%E5%AE%9A_%E7%9B%B4%E6%8E%A5%E5%91%BC%E3%81%B3%E5%87%BA%E3%81%97.md): #name 形式での呼び出しと exist_plugin

## 関連

- [Pkript/文法](Pkript_%E6%96%87%E6%B3%95.md) - 言語仕様
- [Pkript/API](Pkript_API.md) - 組み込み関数とオブジェクト
- [PkriptRunner](PkriptRunner.md) - #pks / &amp;pks; でコードをその場に書いて実行する
