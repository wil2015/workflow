<template>
  <div class="default-wrapper">
    <div class="msg-box">
      <h2>🚧 Tarefa em Construção</h2>
      <p>A tarefa <strong>{{ taskId }}</strong> ainda não possui uma interface Vue.</p>
      <div class="debug">
          Verifique o arquivo <code>taskMapper.js</code>
      </div>
      <button @click="$emit('fechar')">Fechar</button>
    </div>
  </div>
</template>

<script setup>
defineProps({ taskId: String });
</script>

<style scoped>
.default-wrapper { height: 100%; display: flex; align-items: center; justify-content: center; background: #f8f9fa; }
.msg-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
.debug { margin: 20px 0; background: #eee; padding: 10px; font-family: monospace; border-radius: 4px; }
button { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
</style>