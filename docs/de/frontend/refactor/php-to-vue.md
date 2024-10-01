# PHP-View to vue.js

## Backend
* find the correct `PHP`-folder in the [`src/Modules`](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/src/Modules).
* find the `view`-controller

## Frontend
* create a component folder and files in [`client/src/components/`](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/client/src/components)
* or create a page folder and file in [`client/src/components/`](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/client/src/views/pages)
* to register the component or page and create a JavaScript-file in the `PHP`-folder

### Example, Dashboard.
> It is required to place a *page* loader javascript in [`src/Modules`](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/src/Modules) based on the `webpack`-configuration.

```javascript
import { vueRegister, vueApply } from '@/vue'

// View: {Component}
import '@/views/pages/Dashboard/Dashboard.scss'
import Dashboard from '@/views/pages/Dashboard/Dashboard.vue'

vueRegister({
  Dashboard,
})
vueApply('#dashboard')
```

When replacing a complete page, you could add the page to the [base.twig#L63](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/templates/layouts/base.twig#L63). Which gives you more control over the page because the `else` uses an old grid, which won't stop using.

```twig
{% if route in 'dashboard,index,content'|split(',') %}
```

> Maybe you need to extend [shame-old-style-corrections.scss#L1-4](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/client/src/scss/shame-old-style-corrections.scss#L1-4). To remove the margin.


## Hard part
* Find the code position, where **HTML** is in use.
* Extract the used variables

<details><summary>Some examples</summary>

```php
return $this->twig->render('pages/FoodSharePoint/foodSharePointTop.html.twig', [
	'food_share_point' => $this->foodSharePoint,
]);
```
```php
public function checkFoodSharePoint(array $foodSharePoint): string
	{
		$htmlEscapedName = htmlspecialchars($foodSharePoint['name']);
		$content = '';
		if ($foodSharePoint['pic']) {
			$content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.pic'),
				'<img src="' . $foodSharePoint['pic']['head'] . '" alt="' . $htmlEscapedName . '" />'
			);
		}

		$content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.address'),
			$foodSharePoint['anschrift']
			. '<br />'
			. $foodSharePoint['plz'] . ' ' . $foodSharePoint['ort']
		);

		$content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.description'),
			$this->sanitizerService->markdownToHtml($foodSharePoint['desc'])
		);

		$content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.addedOn'),
			date('d.m.Y', $foodSharePoint['time_ts'])
		);

		$fsName = $foodSharePoint['fs_name'] . ' ' . $foodSharePoint['fs_nachname'];
		$content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.addedBy'),
			'<a href="/profile/' . (int)$foodSharePoint['fs_id'] . '">' . $fsName . '</a>'
		);

		return $this->v_utils->v_field(
			$content,
			$this->translator->trans('fsp.acceptName', ['{name}' => $foodSharePoint['name']]),
			['class' => 'ui-padding']
		);
	}

```
```php
public function joininfo(): string
	{
		return '
		<div class="page-container page-joininfo">
			<h1> ' . $this->translator->trans('startpage.join_rules') . ' </h1>
			<h3> ' . $this->translator->trans('startpage.join_welcome') . ' </h3>
			<p> ' . $this->translator->trans('startpage.respect') . ' <br><b>' . $this->translator->trans('startpage.register') . '</b></p>
			<h3> ' . $this->translator->trans('startpage.forstores') . ' </h3>
			<p> ' . $this->translator->trans('startpage.together') . ' </p>'
// the paragraph invites to foodsharing - both individuals and stores
			. $this->v_utils->v_field('
			<div class="reddot">
			<h5><span>1</span>  ' . $this->translator->trans('startpage.honest') . '</h5>
			<p> ' . $this->translator->trans('startpage.telltruth') . '</p>
			<h5><span>2</span>  ' . $this->translator->trans('startpage.followrules_a') . '</h5>
			<p> ' . $this->translator->trans('startpage.followrules_b') . ' ' . $this->translator->trans('startpage.followrules_c') . '</p>
			<p> ' . $this->translator->trans('startpage.notallowed') . '</p>'
// the paragraph states do`s and don`t`s for foodsharing, the next ones talk about how one should interact in the community
			. '<h5><span>3</span> ' . $this->translator->trans('startpage.beresponsible') . '</h5>
			<p>30<span style="white-space:nowrap">&thinsp;</span>% ' . $this->translator->trans('startpage.responsibility') . '</p>
			<h5><span>4</span> ' . $this->translator->trans('startpage.bedependable') . '</h5>
			<p>' . $this->translator->trans('startpage.dependability') . '</p>
			<h5><span>5</span> ' . $this->translator->trans('startpage.makeproposals') . '</h5>
			<p>' . $this->translator->trans('startpage.proposals') . '</p>
			</div>', $this->translator->trans('startpage.etiquette'), ['class' => 'ui-padding']) . '
			<p class="buttons"><br><a href="/?page=register" style="font-size:180%;" class="button">' . $this->translator->trans('startpage.registernow') . '</a><br></p>
		</div>
		';
	}

```

</details>

## Showing the Vue-Component
When you found the correct `PHP`-view file, you can register the `Vue`-component in it.

```php
$this->vueComponent('component-id', 'component-name', $params)
```

##### Example, [DashboardView.php](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/src/Modules/Dashboard/DashboardView.php#L9-12)
```php 
public function index($params): string
{
	return $this->vueComponent('dashboard', 'dashboard', $params);
}
```
And call it in [DashboardControl.php](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/src/Modules/Dashboard/DashboardControl.php#L68) where the `$params` is filled with information.
```php
	...
	$this->pageHelper->addContent($this->view->index($this->params), CNT_MAIN);
}
```

## VUE
In the `Vue`-components, add some `props` where you need and define in `PHP`.

##### Example, [`Dashboard.vue`](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/client/src/views/pages/Dashboard/Dashboard.vue#L171-176)
```javascript
 props: {
    broadcast: { type: Object, default: () => null },
    quiz: { type: Object, default: () => null },
    errors: { type: Array, default: () => null },
    events: { type: Object, default: () => ({ accepted: null, invites: null }) },
  },
```

