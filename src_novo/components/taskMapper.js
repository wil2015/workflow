// taskMapper.js

// Verifica se esses arquivos existem na pasta components com ESSES nomes exatos:
import SolicitacoesList from './SolicitacoesList.vue'; 
import FornecedoresList from './FornecedoresList.vue'; 
import CotacaoValores   from './CotacaoValores.vue';   
import GradeComparativa from './GradeComparativa.vue'; 
import DefaultTask      from './DefaultTask.vue';      

export const taskMap = {
    // Verifica se os IDs aqui batem com o seu desenho no Camunda/BPMN:
    'Activity_SelecionarSolicitacao':   SolicitacoesList,
    'Activity_SelecionarFornecedores':  FornecedoresList,
    'Activity_ClassificarValores':           CotacaoValores,
    'Activity_Grade':           GradeComparativa
};

export function getComponentForTask(taskId) {
    return taskMap[taskId] || DefaultTask;
}