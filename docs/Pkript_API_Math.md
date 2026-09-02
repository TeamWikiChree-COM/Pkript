# Pkript/API/Math

数学関数のオブジェクト。

## リファレンス

### 丸めと符号

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| Math.floor(n) | Number | 切り捨て |
| Math.ceil(n) | Number | 切り上げ |
| Math.round(n) | Number | 四捨五入 |
| Math.trunc(n) | Number | 小数部を捨てる（0方向へ切り捨て） |
| Math.abs(n) | Number | 絶対値 |
| Math.sign(n) | Number | 符号（-1 / 0 / 1） |
| Math.min(a, b, ...) | Number | 最小値 |
| Math.max(a, b, ...) | Number | 最大値 |
| Math.random() | Number | 0以上1未満の乱数 |

### 累乗と平方根

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| Math.sqrt(n) | Number | 平方根。負の数は NaN |
| Math.cbrt(n) | Number | 立方根 |
| Math.pow(a, b) | Number | a の b 乗 |
| Math.hypot(a, b, ...) | Number | 各値の2乗和の平方根 |

### 指数と対数

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| Math.exp(n) | Number | e の n 乗 |
| Math.log(n) | Number | 自然対数。0 は -Infinity、負の数は NaN |
| Math.log2(n) | Number | 2を底とする対数 |
| Math.log10(n) | Number | 10を底とする対数 |

### 三角関数

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| Math.sin(n) / Math.cos(n) / Math.tan(n) | Number | 引数はラジアン |
| Math.asin(n) / Math.acos(n) | Number | 範囲外（-1未満または1超）は NaN |
| Math.atan(n) | Number | 逆正接 |
| Math.atan2(y, x) | Number | y/x の逆正接（象限を考慮） |

### 定数

| 名前 | 値 |
| --- | --- |
| Math.PI | 円周率 |
| Math.E | 自然対数の底 |
| Math.LN2 / Math.LN10 | 2 / 10 の自然対数 |
| Math.LOG2E / Math.LOG10E | e の2 / 10を底とする対数 |
| Math.SQRT2 / Math.SQRT1_2 | 2 / 0.5 の平方根 |

## 使用法

### 値を範囲内に収める

```js
const clamped = Math.min(Math.max(value, 0), 100);
```

### 2点間の距離を求める

```js
const d = Math.hypot(x2 - x1, y2 - y1);
```
