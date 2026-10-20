# 设计系统 · WinUI 3 × 液态玻璃

> 这套皮肤不是「加个 blur 就完事」：它模拟的是 Windows 11 的材质分层（Mica → Acrylic/液态玻璃 → 内容），以及 Fluent 的控件几何与动效节奏。改样式前先理解三层材质与令牌，否则很容易做出「糊成一片」的玻璃。

## 1. 设计原则

1. **深度来自描边，不来自阴影**。WinUI 的层级感是 1px 亮边 + 1px 暗边的边缘折射；投影只负责「离底面的距离」，且始终柔和、低透明。
2. **玻璃必须透出东西**。`backdrop-filter` 只有在背后有颜色/纹理时才产生「液态」感。页面底层永远铺 `.xi-backdrop`（Mica 色场），玻璃卡片叠在它上面。
3. **颜色只有一个源**。所有品牌色都从 `--xi-main` 派生（`color-mix`），换主题色只需改一个变量。
4. **控件几何统一**：按钮/输入框 8px 圆角（`--xi-ctrl-radius`），卡片 16px（`--xi-radius`），胶囊标签 999px。
5. **动效短而准**：120–200ms，进入用减速曲线、退出用加速曲线；不做位移夸张的弹跳。
6. **深浅色是两套材质，不是滤镜**。深色下玻璃是深灰半透明 + 白色细边，绝不 `filter: invert()`。

## 2. 令牌（`assets/css/main.css:6-73`）

### 品牌与几何

| 令牌 | 浅色 | 深色 | 说明 |
| --- | --- | --- | --- |
| `--xi-main` | `#2bb39a` | 同 | 品牌色（薄荷绿），可在后台换预设或自定义 |
| `--xi-main-soft` | `main` 12% 透明 | 同 | 品牌色浅底（hover 背景、标签底） |
| `--xi-main-line` | `main` 35% | 同 | 品牌色描边 |
| `--xi-main-deep` | `main` 80% + `#062a23` | `main` 70% + 白 | 品牌色文字/链接 hover |
| `--xi-radius` | `16px` | 同 | 卡片圆角（后台可调） |
| `--xi-ctrl-radius` | `8px` | 同 | 控件圆角（= radius 的一半，由 `xi_ctrl_radius()` 计算） |
| `--xi-blur` | `24px` | 同 | 玻璃模糊半径（后台可调） |

### 材质

| 令牌 | 浅色 | 深色 |
| --- | --- | --- |
| `--xi-bg` | `#edf4f2` | `#0f1614` |
| `--xi-mica-1` | `main` 26% + 白 | `main` 30% + `#0f1614` |
| `--xi-mica-2` | `#dff3f6` | `#0f2427` |
| `--xi-mica-3` | `#fbfdfc` | `#121a18` |
| `--xi-glass` | `rgba(255,255,255,.56)` | `rgba(30,40,37,.5)` |
| `--xi-glass-hover` | `rgba(255,255,255,.72)` | `rgba(38,50,47,.62)` |
| `--xi-glass-strong` | `rgba(255,255,255,.86)` | `rgba(28,36,34,.9)` |
| `--xi-solid` | `#fbfdfc` | `#1b2422` |
| `--xi-edge-hi` | `rgba(255,255,255,.95)` | `rgba(255,255,255,.22)` |
| `--xi-edge-lo` | `rgba(255,255,255,.15)` | `rgba(255,255,255,.03)` |
| `--xi-stroke` | `rgba(15,45,40,.07)` | `rgba(255,255,255,.07)` |
| `--xi-stroke-strong` | `rgba(15,45,40,.14)` | `rgba(255,255,255,.14)` |
| `--xi-subtle` / `--xi-subtle-2` | `rgba(15,45,40,.045/.08)` | `rgba(255,255,255,.05/.09)` |

### 文字与阴影

| 令牌 | 浅色 | 深色 |
| --- | --- | --- |
| `--xi-text` | `#17221f` | `#e8f0ee` |
| `--xi-text-2` | `#4a5955` | `#b3c1bd` |
| `--xi-text-3` | `#84928e` | `#7f8f8a` |
| `--xi-shadow` | `0 1px 2px rgba(10,40,34,.04), 0 10px 30px -12px rgba(10,40,34,.16)` | `0 1px 2px rgba(0,0,0,.2), 0 12px 32px -12px rgba(0,0,0,.55)` |
| `--xi-shadow-hover` | 上者加强 | 上者加强 |

### 字体

- `--xi-font`：`Segoe UI Variable Text` → `Segoe UI` → `system-ui` → `PingFang SC` → `Microsoft YaHei UI` → `Microsoft YaHei`。
- `--xi-font-display`：Segoe UI Variable Display 家族（标题、大数字）。
- `--xi-mono`：Cascadia Code → Consolas（代码块、下载信息）。
- 正文 15px / 行高 1.75；正文区 `.xi-content` 16px / 1.9；容器 `.xi-container` 最大 1180px，左右 20px 内边距。

## 3. 三层材质

```
.xi-backdrop  固定 inset:0、z-index:-1、pointer-events:none
              3 个 radial-gradient 色场 + 1 个纵向 linear-gradient
      ↓
.xi-glass / .xi-card   半透明 + blur(--xi-blur) saturate(180%) + inset 高光 + --xi-shadow
      ↓
弹层（下拉、登录弹窗、通知）  --xi-glass-strong（更不透明，保证文字可读）
```

- `.xi-glass` 与 `.xi-card` 共用同一套材质规则（`main.css:126-136`）；`.xi-widget.xi-card-glass` 也走这条。
- `.xi-widget.xi-card-solid` 用 `--xi-solid` 实底（菜单、代码块、表格这类需要高可读性的容器）。
- `.xi-widget.xi-card-none` 无底（通栏区块）。

**回退**：`@supports not (backdrop-filter…)` 时把玻璃替换成 `--xi-glass-strong`（`main.css:163-165`），保证老浏览器不出现「透明到看不清」。

## 4. 高光描边怎么做的

```css
.xi-glass::before {
  content: ""; position: absolute; inset: -1px;
  border-radius: inherit; padding: 1px;
  background: linear-gradient(150deg, var(--xi-edge-hi), var(--xi-edge-lo) 35%,
                              var(--xi-edge-lo) 65%, var(--xi-edge-hi));
  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
}
```

原理：用「内容盒减去整盒」得到一圈 1px 环，再填渐变 —— 左上亮、右下暗，像光线掠过玻璃边缘。要点：

- 伪元素必须 `pointer-events: none`，`inset: -1px` 让环压在边框外沿。
- 如果不支持 `mask-composite`，环会退化成实心渐变块，所以**新组件复用这个写法时不要改结构**。
- 环是 `::before`，组件自身需要 `::after` 时先检查是否被占用。

## 5. 控件规范

| 控件 | 类名 / 位置 | 规格 |
| --- | --- | --- |
| 按钮 | `.xi-btn`（`main.css:168`） | min-height 32px、padding 5/16、`--xi-ctrl-radius`、1px 边（bottom 用 `--xi-stroke-strong`）、`:hover` 换 `--xi-ctrl-hover`、`:active` `scale(.98)` |
| 小按钮 | `.xi-btn-sm` | 28px 高、13px 字 |
| 主按钮 | `.xi-btn-accent` | 底 `--xi-main`、文字白、边用 `color-mix(…, #000)` 压暗 |
| 图标按钮 | `.xi-btn-icon` | 36×36、`display: grid; place-items: center` |
| 悬浮导航 | `.xi-nav` / `.xi-menu`（`:265`、`:303`） | 玻璃胶囊、sticky、滚动时加投影 |
| 导航选中 | NavigationView 指示条（`:323`） | 选中项底部/侧边 2–3px 品牌色条 + 文字变 `--xi-main-deep` |
| 信息条 | `.xi-infobar`（`:1319`） | 变体 `-success #0f9d58` / `-warning #d39300` / `-error #d13438`，图标色用局部变量 `--c` |
| 分页 | PipsPager 风格（`:914`） | 圆点当前项拉长成胶囊 |
| 列表悬停 | ListView 风格（`:1175`） | `--xi-subtle` 整行底、圆角 8px |
| 选项卡 | SelectorBar 风格（`:1487`） | 胶囊底随选中项滑动 |
| 弹窗 | `--xi-glass-strong` | 1px 亮边 + 大投影，背景加 `rgba(0,0,0,.25)` 遮罩 |
| 输入框 | 继承 `.xi-btn` 几何 | 8px 圆角、`--xi-ctrl` 底、聚焦时 2px 描边 |
| 编辑器工具栏 | CommandBar 风格（`:2012`） | 玻璃条 + 分组分隔线 + 32px 图标按钮 |

**卡片交互**：`.xi-card` 悬停只做 `translateY(-2px)` + `--xi-shadow-hover`，不加缩放（Fluent 的 hover 是「抬起」不是「变大」）。

## 6. 动效

- 时长：状态切换 120–150ms（按钮、链接），位移/展开 180–240ms（卡片、抽屉），页面级过渡 ≤ 300ms。
- 曲线：项目里大量使用 `cubic-bezier`（34 处），统一观感是「快出慢入」；新动效优先沿用相邻组件的曲线值，不要自造。
- 只动 `transform` / `opacity` / `background-color` / `box-shadow`，避免动 `width/height` 造成重排。
- `prefers-reduced-motion: reduce` 在 `main.css:3117` 等处已有整套降级：新动效必须一起放进该媒体查询，或直接不写动画。
- 不用 `will-change` 常驻；仅在打开态临时加。

## 7. 可访问性

- 全局焦点：`:focus-visible { outline: 2px solid var(--xi-text); outline-offset: 2px }`（`main.css:107`）。自定义控件不要 `outline: none` 除非同时提供等价的可见焦点环。
- 屏读文本：`.screen-reader-text`（`main.css:111`）。
- 正文对比度：`--xi-text` 在 `--xi-glass` 上 ≥ 12:1；`--xi-text-2`/`--xi-text-3` 只用于辅助信息，不要用在正文。
- 玻璃上的深色文字：浅色模式玻璃偏白，可读；**深色模式不要用 `--xi-text-3` 放在玻璃上**（对比不足），改用 `--xi-text-2`。
- 交互元素最小点击区 32×32（图标按钮 36）。
- 表单错误必须同时有颜色与图标/文字，不能只靠 `#d13438`。

## 8. 写一个新组件

1. 先判定它属于哪一层：Mica 背景上的**玻璃卡片**、需要高可读性的**实底容器**，还是**浮层**（用 `--xi-glass-strong`）。
2. 结构：容器加 `.xi-glass` 或 `.xi-card`（自动获得玻璃 + 高光边 + 阴影），内部用 `.xi-btn` 系列控件。
3. 颜色/圆角/间距**只用令牌**；需要新色时先加 `:root` 令牌 + `[data-theme="dark"]` 覆盖，再引用。
4. 交互态补齐：`:hover`、`:active`、`:focus-visible`、`[disabled]`、`.is-active`（项目约定用 `is-` 前缀）。
5. 在 `prefers-reduced-motion` 块里补降级；在无 `backdrop-filter` 的浏览器里确认可读（必要时用 `--xi-solid`）。
6. 区块样式放 `assets/css/blocks.css`（前后台共用），只给编辑器用的放 `blocks-editor.css`；不要往 `main.css` 里堆业务样式。

## 9. 反模式

- ❌ 在玻璃卡片里再套玻璃卡片（模糊叠模糊 → 灰雾）。
- ❌ 给 `backdrop-filter` 元素加 `opacity < 1` 或 `filter`（会创建新层叠上下文，玻璃失效或变糊）。
- ❌ 写死 `#2bb39a` / `border-radius: 16px`（绕过令牌，换肤即失效）。
- ❌ 用 `prefers-color-scheme` 做主题切换（主题用 `html[data-theme]`，由 `xi_theme_mode_script()` 在首帧前写入）。
- ❌ 深色模式直接 `filter: invert()` 浅色图（图片、Logo、二维码会失真）。
- ❌ 大面积纯黑/纯白背景配玻璃（没有色彩层次，玻璃看起来像灰色蒙版）。
- ❌ 长文正文使用玻璃底（滚动时背后色场移动，阅读疲劳）；长文用 `--xi-solid` 或 `--xi-bg`。
