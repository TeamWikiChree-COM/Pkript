# Pkript/API/Number

数値型のメソッドリファレンス。

## リファレンス

### num.toFixed(digits)

```text
num.toFixed(digits)
```

- digits: Number: 小数点以下の桁数

戻り値: String

### num.toPrecision([digits])

```text
num.toPrecision(digits)
```

- digits: Number: 有効数字の桁数（1〜100）

戻り値: String

指数部が -6 未満、または digits 以上のときは指数表記になる（JavaScriptと同仕様）。digits を省略すると String(num) と同じ。

### num.toString([radix])

```text
num.toString(radix)
```

- radix: Number: 基数（2〜36、省略時は10）

戻り値: String

10以外の基数では小数部は捨てられる。

### num.valueOf()

戻り値: Number: 数値そのもの。

## 使用法

### 小数を指定桁数で表示する

```js
return (3.14159).toFixed(2);   // "3.14"
```

### 16進数に変換する

```js
return (255).toString(16);     // "ff"
```
