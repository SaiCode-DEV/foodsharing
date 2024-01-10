# CI/CD Style guide

This style guide is visible as [modal](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/client/src/views/partials/Modals/StyleGuideModal.vue) on the website, when you click `Navigation → “Hilfe nötig?” → Styleguide`.

# Colors
We have **7** adjusted colors. Each color has a range from **100 (lighter)** to **900 (darker)** and **500** is the base color.


```scss title=colors.scss#L1-7
$primary: #533a20;
$secondary: #64ae24;
$gray: #5e636e;
// States
$warning: #cc990e;
$success: $secondary;
$danger: #cf3a00;
$info: #006bcf;
```

> [client/src/scss/colors.scss](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/client/src/scss/colors.scss)

## Variables
The color variables are generated, which makes them invisible for the autocomplete of IDEs.
The generations of the individual stepped variables happen in [colors.scss#L34-61](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/client/src/scss/colors.scss#L34-61) if this is from interests.

:::caution
`var(--fs-color-{name}-{step})` → `var(--fs-color-primary-500)` for the primary base color.
:::

```scss title=colors.scss#L62-65
--fs-color-role-ambassador: var(--fs-color-warning-500);
--fs-color-role-storemanager: var(--fs-color-secondary-500);
--fs-color-role-foodsaver: var(--fs-color-primary-500);
--fs-color-role-foodsharer: var(--fs-color-primary-500);
--fs-color-role-jumper: var(--fs-color-primary-500);

--fs-color-type-stores: var(--fs-color-danger-500);
--fs-color-type-baskets: var(--fs-color-secondary-500);
--fs-color-type-foodshare-points: var(--fs-color-warning-500);
--fs-color-type-communities: var(--fs-color-info-500);
```
> [colors.scss#L62-65](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/client/src/scss/colors.scss#L62-65), are some special cases for colors for roles, stores, baskets etc …

## Usage

:::tip **Good**
 ```scss
color: var(--fs-color-primary-500);
background-color: var(--fs-color-primary-200);
border-color: var(--fs-color-primary-300);
```
:::

:::danger **Bad**
```scss
color: $primary;
background-color: #ddd8d2;
border-color: #{tint($primary, 25%)};
```
:::

# Icons
* [Font Awesome V5 [FREE]](https://fontawesome.com/v5/search?m=free&s=solid%2Cbrands)
* [Animated Icons](https://fontawesome.com/v5/docs/web/style/animate#contentHeader)

# Fonts
* Headlines: [Alfa Slab One](https://fonts.google.com/specimen/Alfa+Slab+One)
* Body: sans-serif

# Components
* [Bootstrap 4.6](https://getbootstrap.com/docs/4.6/getting-started/introduction/) (Loaded through Bootstrap-VUE)
* [Bootstrap-VUE 2.18.1](https://www.bootstrap-vue.org/)
