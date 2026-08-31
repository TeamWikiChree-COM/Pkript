#author("2026-08-31T21:40:00+09:00","","")

# Pkript/リファレンス/API/Math

数学関数のオブジェクト。

## リファレンス

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| Math.floor(n) | Number | 切り捨て |
| Math.ceil(n) | Number | 切り上げ |
| Math.round(n) | Number | 四捨五入 |
| Math.abs(n) | Number | 絶対値 |
| Math.min(a, b, ...) | Number | 最小値 |
| Math.max(a, b, ...) | Number | 最大値 |
| Math.random() | Number | 0以上1未満の乱数 |

## 使用法

### 値を範囲内に収める

```js
const clamped = Math.min(Math.max(value, 0), 100);
```
