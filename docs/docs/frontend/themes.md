# Themes

To create a new theme, you need to create a new file like theme/pink.scss

```scss
.pink-mode {
    @each $name, $color in $dark-colorlist {
        $step: 110%;
        @for $i from 1 through 9 {
            $index: $i * 100;
            @if $i < 5 {
                $step: $step - 15%;
                --fs-color-#{$name}-#{$index}: #{shade($color, $step)};
            }

            @if $i == 5 {
                $step: 0%;
                --fs-color-#{$name}-#{$index}: #{$color};
            }

            @if $i > 5 {
                $step: $step + 20%;
                --fs-color-#{$name}-#{$index}: #{tint($color, $step)};
            }
        }
    }

    @each $name, $color in $dark-colorlist {
        $step: 100;
        @for $i from 1 through 9 {
            $index: $i * 10;
            $alpha: -($step - $index) * 1%;
            --fs-color-#{$name}-alpha-#{$index}: #{scale-color($color, $alpha: $alpha)};
        }
    }

    --fs-color-background: #{scale-color(shade($secondary, 94%), $saturation: -50%)};
     --fs-color-elevated: #{scale-color(shade($secondary, 90%), $saturation: -80%)};
    --fs-color-white: #0e0e0e;
    --fs-color-black: #fefefe;
    --fs-color-light: var(--fs-color-gray-100);
    --fs-color-dark: var(--fs-color-gray-600);
    --fs-color-text: var(--fs-color-gray-500);

    --fs-shadow: 0px 1.2px 5.3px rgba(0, 0, 0, 0.2),
                 0px 4px 17.9px rgba(0, 0, 0, 0.3),
                 0px 18px 80px rgba(0, 0, 0, 0.4);

    //chat only darkmode overrites:
    --chat-content-bg-color: var(--fs-color-primary-100) !important;
}
```

Then you need to add the theme to 
`client/src/stores/theme.js`
(the name without the `-mode`)

with the icon you want to use for the theme

```js
export const themes = {
  ...
  {
    value: 'pink', // the class name (without the -mode)
    text: i18n('theme_switcher.theme.pink'), // the display name
    icon: 'fa-moon',
    isDark: true,
  },
}
```
the isDark property determines the theme for not stylable elements like the chat and map.

Also add the theme name to the translation file.

