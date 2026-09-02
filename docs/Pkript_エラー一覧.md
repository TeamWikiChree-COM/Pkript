# Pkript/エラー一覧

Pkript の実行時に表示される主なエラーメッセージと対処法です。

エラーは **Pkript Error: &lt;メッセージ&gt; (スクリプト名:行番号:列番号)** の形式で画面に出力されます。JavaScript のスタックトレースと同じ書き方です。メッセージは英語で、対応する状況が JavaScript にあるものはその言い回しに合わせています。

## 構文エラー (Parse Error)

| エラーメッセージ | 原因 | 対処法 |
| --- | --- | --- |
| Unexpected token, expected ';' | 文末のセミコロンが抜けているか、文法上不正な位置にトークンがあります。 | 行末にセミコロンを付けるか、改行を入れてください。 |
| Unterminated string | 文字列リテラルの引用符（" または '）が閉じていません。 | 末尾の引用符を確認してください。 |
| A string may not contain a newline | 文字列の途中で改行が入っています。 | \n を使うか + で文字列を連結してください。 |
| Unterminated comment | 複数行コメント /* ... */ が閉じられていません。 | 末尾に */ を追加してください。 |
| { is not closed | 波括弧 { の開きに対応する } がありません。 | ブロックの対応を確認してください。 |
| A function may only be declared at the top level | function 宣言が関数やif文の中に書かれています。 | アロー関数（const f = () =&gt; ...）を使うか、外側で定義してください。 |
| Identifier '◯◯' has already been declared | 同じ名前の関数、または関数とグローバル変数が重複しています。 | 名前を一意に変更してください。関数と変数は同じ名前空間を使います。 |
| function or a variable declaration is required | 関数の外側に、変数宣言でも関数定義でもない文が置かれています。 | 処理は関数内に書いてください。 |
| const requires an initial value | const に値を代入していません。 | 初期値を書くか、let を使ってください。 |
| Unterminated template literal | バッククォートが閉じていません。 | 末尾のバッククォートを確認してください。 |
| ${} is not closed | テンプレートの ${ に対応する } がありません。 | 括弧の対応を確認してください。 |
| Empty ${} in a template literal | ${} に式が書かれていません。 | 式を書くか、${} を消してください。 |
| ${} has trailing tokens | ${} に式が2つ以上あります。 | 式は1つだけ書いてください。 |
| switch is not closed | switch の { に対応する } がありません。 | ブロックの対応を確認してください。 |
| Only one default is allowed | 1つの switch に default が2つ以上あります。 | 1つに減らしてください。 |
| 'catch' expected but found ◯◯ | try に catch が続いていません。 | try { } catch (err) { } の形で書いてください。finally は使えません。 |
| Invalid escape \◯ | 文字列で対応していないエスケープを使いました。 | 使えるのは \n \t \r \ \" \' です。テンプレートでは \` と \$ も使えます。 |
| Unexpected ◯ after a number literal | 123abc や 0x のように、数値の直後に識別子の文字が続いています。 | 数値と識別子の間に空白か演算子を入れてください。桁区切りの _ は数字と数字の間にだけ書けます。 |
| Undefined label '◯◯' | break や continue に、宣言されていないラベルを書きました。 | ラベル名を確認してください。ラベルは break / continue と同じ行に書きます。 |
| Label '◯◯' has already been declared | 入れ子の内側で外側と同じラベル名を使いました。 | どちらかの名前を変えてください。 |
| A continue label must name a loop | ブロックに付けたラベルへ continue しました。 | continue できるのはループのラベルだけです。break を使うか、ラベルをループに付けてください。 |
| Unterminated regular expression | / で始まる正規表現が閉じていないか、途中で改行しています。 | 終わりの / を書いてください。除算のつもりなら前の字句を確認してください。 |
| Invalid regular expression | PCREがパターンを解釈できませんでした。 | 括弧やエスケープの対応を確認してください。 |
| Regular expression too long | パターンが上限（PKRIPT_MAX_REGEX: 既定512バイト）を超えました。 | パターンを分割してください。 |
| Invalid regular expression flag ◯ is not allowed / is duplicated | 使えるフラグは g i m s の4つです。 | フラグを確認してください。 |
| Regular expression: ◯◯ is not allowed | 再帰 (?R)、サブルーチン呼び出し (?1)、コールアウト (?C)、\K や \C を使いました。 | これらは照合ではなく実行になるため使えません。書き方を変えてください。 |
| Invalid character ◯ | Pkript で使えない文字が現れました。 | 全角記号や、対応していない演算子が混ざっていないか確認してください。 |

## JSXのエラー

[Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md) の記法に関するエラーです。PKRIPT_JSX が 0 の場合は発生しません。

| エラーメッセージ | 原因 | 対処法 |
| --- | --- | --- |
| ◯◯ is not closed | 開始タグに対応する閉じタグがありません。 | &lt;/タグ名&gt; を書くか、空要素なら &lt;タグ名 /&gt; と閉じてください。 |
| Closing tag ◯◯ does not match ◯◯ | 開始タグと閉じタグのタグ名が違います。 | 入れ子の対応を確認してください。 |
| Unterminated JSX tag | タグの途中でスクリプトが終わっています。 | &gt; を書いてください。 |
| Missing &gt; on a JSX tag | 属性の後ろに &gt; がありません。 | タグを閉じてください。 |
| Missing &gt; on a closing tag | 閉じタグに &gt; がありません。 | &lt;/タグ名&gt; の形にしてください。 |
| A tag name is required | &lt; の直後にタグ名がありません。 | HTMLコメント &lt;!-- --&gt; は書けません。比較演算子として使いたい場合は前後に空白を入れてください。 |
| An attribute name is required | 属性を書く位置に名前がありません。 | 属性名を書くか、タグを閉じてください。 |
| Missing attribute value | = の後ろに値がありません。 | "文字列" か {式} を書いてください。 |
| An attribute value must be a string or {expression} | 属性値が引用符でも波括弧でもありません。 | class="a" または class={a} の形にしてください。 |
| Unterminated attribute value | 属性値の引用符が閉じていません。 | 引用符を確認してください。 |
| Empty {} in an attribute | 属性の {} に式が書かれていません。 | 式を書いてください。 |
| JSX {} ... | 子要素の {} の書き方が不正です。 | 式を1つだけ書いてください。 |
| Spread attributes are not supported | {...obj} を使いました。 | 属性を1つずつ書いてください。 |

## 実行時エラー (Runtime Error)

| エラーメッセージ | 原因 | 対処法 |
| --- | --- | --- |
| Entry point ◯◯ was not found | 呼び出し種別に対応する関数（convert/inline/action）が定義されていません。 | plugin_&lt;名前&gt;_convert 等の関数を定義してください。 |
| ◯◯ is not defined | 定義されていない変数を参照しました。 | 変数名の誤字を確認するか、代入を行ってください。 |
| Assignment to constant variable ◯◯ | const 定数に再代入しようとしました。 | let または var を使ってください。 |
| ◯◯ is not a function | 関数以外の値を呼び出そうとしました。 | 対象の変数が関数か確認してください。 |
| Cannot read property '◯◯' of an object | オブジェクトに存在しないプロパティに直接アクセスしました。 | o["prop"] を使うと未存在時に空文字になります。 |
| Negative array index | 負のインデックスで代入しようとしました。 | 0以上のインデックスを指定してください。 |
| Division by zero | 0 で割り算または剰余を行いました。 | 除数が 0 にならないよう分岐してください。 |
| ◯◯ is not iterable | Array と String 以外を for..of で回しました。 | Object を回すなら for..in か Object.keys() を使ってください。 |
| ◯◯ is not iterable with for..in | Object, Array, String 以外を for..in で回しました。 | 対象の型を確認してください。 |
| ◯◯ cannot be used as a number | 数値に変換できない値を計算に使いました。 | Number() で変換するか、値を確認してください。 |
| ◯◯ is not an Object | Object.keys などに Object 以外を渡しました。 | 引数の型を確認してください。 |
| JSON is empty | 空の文字列を JSON.parse に渡しました。 | 中身のあるJSON文字列を渡してください。 |
| Cannot parse as JSON | 不正な形式の文字列を JSON.parse に渡しました。 | JSONの構文を確認するか、try / catch で処理してください。 |
| JSON has a cycle | 循環参照を含むオブジェクトや配列を JSON.stringify に渡しました。 | 循環構造を解消してください。 |
| Cannot convert to JSON | JSON.stringify で変換に失敗しました。 | 渡した値を確認してください。 |
| Date format too long | date.format に渡した書式文字列が上限（64バイト）を超えました。 | 書式文字列を短くしてください。 |
| Reduce of empty array with no initial value | 要素の無い配列に、初期値を渡さずに reduce を呼びました。 | 初期値を渡すか、先に長さを確認してください。JavaScript が TypeError を投げる場面です。 |
| ◯◯ is not a RegExp | match / matchAll / search に正規表現以外を渡しました。 | /パターン/ の形で渡してください。文字列は使えません。 |
| Regular expression replace failed / Regular expression split failed | PCREが処理を完了できませんでした。 | パターンと対象を確認してください。 |
| repeat count is negative | repeat メソッドに 0 未満の数値を渡しました。 | 0 以上の回数を指定してください。 |

## リソース制限エラー

| エラーメッセージ | 原因 | 対処法 |
| --- | --- | --- |
| Too many evaluation steps | ステップ数が上限（PKRIPT_MAX_STEPS: 既定1,000,000）を超えました。 | 処理を効率化してください。 |
| Execution time exceeded | 実行時間が制限（PKRIPT_MAX_TIME: 既定3秒）を超えました。 | 計算量を減らしてください。 |
| Too many loop iterations | 1ループの反復回数が上限（PKRIPT_MAX_LOOP: 既定100,000）を超えました。 | 終了条件を確認してください。 |
| Maximum call stack size exceeded | 再帰呼び出しが上限（PKRIPT_MAX_DEPTH: 既定64）を超えました。 | 再帰の停止条件を確認してください。 |
| Regular expression backtrack limit exceeded | 1回の照合でのバックトラックが上限（PKRIPT_REGEX_BACKTRACK: 既定100,000）を超えました。いわゆる破滅的バックトラックです。 | (a+)+ のような入れ子の繰り返しを避け、パターンを具体的にしてください。一致しなかったのではなく**処理を打ち切った**ため、このエラーは catch できません。 |
| Regular expression subject is not valid UTF-8 | 照合する文字列が正しいUTF-8ではありません。 | 入力の文字コードを確認してください。 |
| JSON nesting too deep | JSONの深さが上限（PKRIPT_MAX_DEPTH: 既定64）を超えました。 | 入れ子構造を浅くしてください。 |
| String too long | 文字列の長さが上限（PKRIPT_MAX_STRING: 既定1MB）を超えました。 | 生成する文字列を小さくしてください。 |
| Too many array elements | 配列の要素数が上限（PKRIPT_MAX_ARRAY: 既定10,000）を超えました。 | 要素数を抑えてください。 |
| Too many wiki.convert() calls | wiki.convert の呼び出し回数が上限（PKRIPT_MAX_CONVERT: 既定32回）を超えました。 | 変換回数を減らしてください。 |
| wiki.convert() nesting too deep | wiki.convert が変換したテキストから、さらに Pkript が呼ばれています。 | #pkript を含むテキストを変換していないか確認してください。 |
| Memory limit exceeded | 使用メモリが上限（PKRIPT_MAX_MEMORY: 既定 memory_limit の3/4）を超えました。 | 大きな文字列や配列をためこまないでください。 |
| Too many page reads | wiki.exists / source / pages / link の回数が上限（PKRIPT_MAX_READS: 既定5,000回）を超えました。 | ループの中でページを引かないよう見直してください。 |
| Too many pages | wiki.pages の結果が上限（PKRIPT_MAX_PAGES: 既定1,000件）を超えました。 | prefix で絞り込んでください。 |
| Too many page writes | 書き込み回数が上限（PKRIPT_MAX_WRITES: 既定4回）を超えました。 | 1リクエストでの書き込みを減らしてください。 |
| Page too large | 書き込もうとしたページが上限（PKRIPT_MAX_PAGE_BYTES: 既定512KB）を超えました。 | 書き込む量を減らしてください。 |
| Too many imports / import nesting too deep | 読み込むスクリプトの数か深さが上限（既定16本、深さ4）を超えました。 | ライブラリをまとめてください。 |

これらのエラーは **try / catch で捕まえられません**。捕まえられると上限そのものが意味を失うためです。

## セキュリティ・権限エラー

| エラーメッセージ | 原因 | 対処法 |
| --- | --- | --- |
| Writing a page is only allowed from an action | convert や inline から wiki.write / append を呼び出しました。 | ページの書き込みは action エントリポイント（?plugin=pkript&amp;script=...）からのみ行えます。 |
| Writing a page is only allowed from a POST | GET リクエストでページを書き込もうとしました。 | form の method="post" から送信してください。 |
| Invalid token | CSRFトークンが送信されていないか、一致しません。 | フォームに &lt;input type="hidden" name="pkript_token" value="..."&gt; を含めてください。 |
| This script may not write pages | 未凍結のページスクリプトなどから書き込みAPIを呼び出しました。 | plugin/pkript/script/ フォルダのファイルとして設置してください。 |
| Cannot import a less trusted script | 信頼度の高いスクリプトが編集可能なページスクリプトを import しようとしました。 | 読み込み先のページを凍結するか、ファイルとして設置してください。 |
| Not a writable page name | ページ名が空か、: で始まるページに書き込もうとしました。 | : で始まるページには書き込めません。ページスクリプト自身が置かれている場所だからです。 |
| The page is frozen | 凍結されたページに書き込もうとしました。 | 凍結を解除するか、別のページを指定してください。 |
| No permission to edit the page | $edit_auth_pages で保護されたページに書き込もうとしました。 | 認証するか、別のページを指定してください。 |
| The wiki is read only | PKWK_READONLY が有効です。 | 設定を確認してください。 |
| This environment cannot write pages | PukiWiki の page_write が見つかりません。 | 導入手順を確認してください。 |
| Script not found: ◯◯ | スクリプトが見つかりません。import 先が無い場合もこのメッセージになります。 | ファイル名かページ名を確認してください。 |
| ◯◯ is already defined in ◯◯ | import したスクリプトと関数名か変数名が衝突しています。 | どちらかの名前を変えてください。 |
