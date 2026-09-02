# PkriptRunner

Pkript のコードを、スクリプトファイルにもページにも置かずに、書いたその場で実行するプラグイン。plugin/pks.inc.php。

[Pkript](Pkript.md) 本体（#pkript）が「名前で呼ぶ」のに対し、こちらは「その場に書く」。使い捨ての計算や、ページ1枚のためだけの表組みに向く。

## 呼び出し形式

| 呼び出し | 用途 |
| --- | --- |
| #pks{{ ... }} | ブロック要素 |
| #pks(引数, ...){{ ... }} | ブロック要素、引数つき |
| &amp;pks(コード); | インライン要素 |
| &amp;pks(引数, ...){コード}; | インライン要素、引数つき |

引数は e.args に、key=value の形のものは e.opts にも入る（#pkript と同じ）。

## 書き方

### 手続き型

関数の中身をそのまま書く。e はそこにある。

```js
#pks{{
return "<p>" + htmlsc(e.args[0]) + "</p>";
}}
```

return で終わっていないときは、最後の式がそのまま値になる。式ひとつを書くだけでよい。

```text
&pks(1 + 1);
```

```js
#pks{{
let total = 0;
for (const n of [1, 2, 3]) total = total + n;
total
}}
```

最後が式でなければ（宣言や代入で終わっていれば）値はなく、何も出力されない。

この省略が効くのは手続き型の本体の末尾だけで、自分で定義した関数の中では効かない。値を返さないつもりの関数が最後の式を返してしまわないようにするため。

### main()

関数を定義したいときは main(e) を入口として、完全なスクリプトを書く。

```js
#pks(21){{
function twice(n) {
    return n * 2;
}

function main(e) {
    return twice(Number(e.args[0]));
}
}}
```

どちらの書き方かは、ソースがスクリプトとして解釈できるかどうかで自動的に決まる。書き分けの宣言は要らない。main() を書かずに関数だけを定義するとエラーになる。

## #pkript との違い

|  | #pkript | #pks |
| --- | --- | --- |
| コードの置き場所 | ファイル / :config/pkript/script/ | ページ本文そのもの |
| 入口 | plugin_◯◯_convert(e) など | main(e)、または本体だけ |
| 信頼レベル | 置き場所による（ファイルなら PKRIPT_TRUST_FILE） | ページによる（下記） |
| import | 使える | 使えない |
| action | 使える | 使えない |
| ASTキャッシュ | する | しない |

繰り返し使うもの、他から import されるもの、フォームを処理するものは #pkript で名前をつけて書く。#pks はその場限りのコードのためにある。

## 信頼レベル

コードが載っているページから決まる。ページスクリプト（:config/pkript/script/）とまったく同じ規則。

| ページ | 信頼レベル |
| --- | --- |
| 凍結ページ | PKRIPT_TRUST_FROZEN |
| それ以外 | PKRIPT_TRUST_PAGE |

どちらも PKRIPT_WRITE_MIN_TRUST と PKRIPT_DATA_MIN_TRUST の既定値（PKRIPT_TRUST_FILE）を下回るため、既定では wiki.write() と data.set() は使えない。ウィキの読み取りと出力はできる。

PKRIPT_PAGE_SCRIPT_FROZEN_ONLY を 1 にすると、凍結されていないページでは #pks 自体が実行されない（[Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md) 参照）。

## 実行の上限

ステップ数、実行時間、メモリ、ページ参照回数などの上限は #pkript と共通で、1リクエストの合計に対して効く。1ページに #pks をいくつ置いても、合計が [Pkript/設定/上限値](Pkript_%E8%A8%AD%E5%AE%9A_%E4%B8%8A%E9%99%90%E5%80%A4.md) の上限を超えれば止まる。

## エラー

エラーメッセージの行番号は、ページに書いたとおりの行番号で出る。表記は JavaScript のスタックトレースと同じ スクリプト名:行:列。手続き型で書いた場合に内部で関数に包んでいることは、行番号には現れない。

```js
#pks{{
let a = 1;
return zzz;
}}
```

```text
Pkript Error: zzz is not defined (pks:2:8)
```

エラーの一覧は [Pkript/エラー一覧](Pkript_%E3%82%A8%E3%83%A9%E3%83%BC%E4%B8%80%E8%A6%A7.md)。

## 関連

- [Pkript](Pkript.md) - 言語本体
- [Pkript/文法/コンテキスト](Pkript_%E6%96%87%E6%B3%95_%E3%82%B3%E3%83%B3%E3%83%86%E3%82%AD%E3%82%B9%E3%83%88.md) - e の中身
- [Pkript/API](Pkript_API.md) - 使える組み込み関数
- [Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md) - 設定定数
