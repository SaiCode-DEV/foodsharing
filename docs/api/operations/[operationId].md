---
aside: false
outline: false
title: vitepress-openapi
---

<script setup lang="ts">
import { useRoute, useData } from 'vitepress'
import spec from '../../data/api_dump.json'

const route = useRoute()

const operationId = route.data.params.operationId

</script>

<OAOperation 
:spec="spec"
:operationId="operationId" 
:hideBranding="true"
/>
