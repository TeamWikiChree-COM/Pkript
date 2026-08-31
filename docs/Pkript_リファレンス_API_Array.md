#author("2026-08-31T21:40:00+09:00","","")

# Pkript/リファレンス/API/Array

配列型のメソッドリファレンス。

## リファレンス

### プロパティ

- length: Number: 要素数

### 追加・削除

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| arr.push(val) | Number: 追加後の length | 末尾に追加（破壊的） |
| arr.pop() | any | 末尾の要素を取り出す（破壊的） |
| arr.shift() | any | 先頭の要素を取り出す（破壊的） |
| arr.unshift(val) | Number: 追加後の length | 先頭に追加（破壊的） |

### 検索

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| arr.indexOf(val) | Number（見つからない場合 -1） | 最初の一致位置 |
| arr.includes(val) | Boolean | 要素が存在するか |
| arr.find(fn) | any（見つからない場合 null） | 条件に合う最初の要素 |
| arr.findIndex(fn) | Number（見つからない場合 -1） | 条件に合う最初のインデックス |

### 変換・生成

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| arr.slice(start, [end]) | Array | 部分配列（非破壊） |
| arr.concat(other) | Array | 連結した新しい配列 |
| arr.reverse() | Array | 順序を反転（破壊的） |
| arr.sort([compareFn]) | Array | 並べ替え（破壊的） |
| arr.join(sep) | String | sep で連結した文字列 |

### コールバック系

コールバックのシグネチャ: fn(item, index, array)（reduce のみ fn(acc, item, index, array)）

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| arr.map(fn) | Array | 各要素を変換した新しい配列 |
| arr.filter(fn) | Array | 条件に合う要素の新しい配列 |
| arr.forEach(fn) | なし | 全要素にコールバックを適用 |
| arr.some(fn) | Boolean | 1つでも条件に合えば true |
| arr.every(fn) | Boolean | すべて条件に合えば true |
| arr.reduce(fn, [initial]) | any | 1つの値に畳み込む |

- 走査は配列のコピーに対して行われる。コールバック内で元の配列を変更してもループ回数は変わらない
- some は最初に true になった時点で、every は最初に false になった時点で打ち切る
- 空配列に対して some は false、every は true（JavaScriptと同仕様）
- reduce は初期値を省略すると先頭要素が初期値になる。空配列で初期値を省略するとエラー

## 使用法

### リストをHTMLに変換する

```js
const items = ["りんご", "みかん", "ぶどう"];
const html = items.map((s) => "<li>" + htmlsc(s) + "</li>").join("");
return "<ul>" + html + "</ul>";
```

### 条件で絞り込んでから変換する

```js
const rows = data.get("entries", []);
const active = rows.filter((r) => r.active).map((r) => r.name);
return active.join(", ");
```

### 合計を求める

```js
const total = [10, 20, 30].reduce((a, b) => a + b, 0);   // 60
```

### 更新日時の新しい順にソートする

```js
const pages = wiki.pages("Blog/");
const sorted = pages.slice().sort((a, b) => wiki.time(b) - wiki.time(a));
return sorted.map((p) => "<li>" + wiki.link(p) + "</li>").join("");
```
