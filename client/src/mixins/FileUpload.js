export default {
  methods: {
    /**
     * Returns a promise that loads a file into memory and encodes it as Base64.
     */
    loadFile (file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader()

        reader.onload = (event) => {
          const binaryStr = new Uint8Array(event.target.result)
          let base64 = ''
          binaryStr.forEach((byte) => {
            base64 += String.fromCharCode(byte)
          })
          base64 = window.btoa(base64)
          resolve({
            name: file.name,
            size: file.size,
            type: file.type,
            content: base64,
          })
        }

        reader.onerror = (error) => {
          reject(error)
        }

        reader.readAsArrayBuffer(file)
      })
    },
  },
}
