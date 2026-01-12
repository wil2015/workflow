// 1. Importe seus componentes Vue existentes
import SolicitacoesList from './SolicitacoesList.vue';
import GradeComparativa from './GradeComparativa.vue';
// Adicione outros conforme for migrando (ex: CotacaoValores)
// import CotacaoValores from './CotacaoValores.vue'; 

import DefaultTask from './DefaultTask.vue'; // Vamos criar esse no próximo passo

// 2. Mapeie: ID da Tarefa no BPMN (Camunda) => Componente Vue
export const taskMap = {
    'Activity_SelecionarSolicitacao': SolicitacoesList,
    'Activity_AnalisarGrade': GradeComparativa,
    // 'Activity_LancarValores': CotacaoValores,
};

// 3. Função Helper
export function getComponentForTask(taskId) {
    return taskMap[taskId] || DefaultTask;
}