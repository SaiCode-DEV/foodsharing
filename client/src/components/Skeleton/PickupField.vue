<template>
  <div class="list-group-item">
    <div>
      <b-skeleton class="d-inline-block" width="60%" />
      <b-skeleton width="3em" class="float-right" />
    </div>
    <div class="flex-grow-1">
      <b-skeleton
        class="d-inline-block"
        height="0.75em"
        width="50%"
      />
      <b-skeleton
        width="5em"
        height="1.5em"
        class="float-right"
        style="border-radius: 1em;"
      />
    </div>
  </div>
</template>
