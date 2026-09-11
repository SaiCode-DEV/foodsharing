import { ref, onMounted } from 'vue'

export function useStrawberryHover (defaultName = 'donation-strawberry', highlightSuffix = '-highlight') {
  const strawberryRef = ref(null)

  const defaultSrc = `/img/icon/${defaultName}.svg`
  const highlightSrc = `/img/icon/${defaultName}${highlightSuffix}.svg`

  onMounted(() => {
    const img = new Image()
    img.src = highlightSrc
  })

  function switchImage (type) {
    if (strawberryRef.value) {
      strawberryRef.value.src = type ? highlightSrc : defaultSrc
    }
  }

  return {
    strawberryRef,
    switchImage,
  }
}
