��    }        �   �      �
  K   �
  w   �
     M  >   f     �  k  �  7        L     k  $   }  $   �     �      �  (   �                /     P     c     p  �   �  �   N     L  n   Z  �   �  
   �  !   �     �     �  (        7  Y   M     �     �     �     �     �     �               *     6     B  )   K  1   u     �  +   �     �  9   �  �     &   �  �   �     �     �  �   �     �     �     �  �        �     �  7   �  u   5  &   �  I   �       7   2  (   j  f   �     �                "     %  M   B  �   �     D     G     ^     s     w  '   �     �     �     �  �   �     �  p   �  *        7  $   >     c  �   h  E   W  �   �  0   F      w   S   �   %   �   �   !     �!  D   �!  �   �!     �"  R   �"     M#  �   Q#  X   $  X   r$     �$  %   �$     �$     %     %  #   %     4%     ;%     D%  
   Y%     d%     p%     x%     �%     �%  �  �%  W   u'  }   �'     K(  Y   k(     �(  �  �(  =   �+  "   �+     �+      �+  0   ,     F,  #   L,  0   p,     �,     �,  (   �,     �,     -     -  �   2-  ,  &.     S/  w   g/    �/     �0  '   1  
   51  (   @1  "   i1     �1  `   �1     	2     2     #2     22     D2     _2     {2     �2     �2     �2     �2  3   �2  2   3     A3  <   T3     �3  J   �3  �   �3  /   �4  �   �4  %   �5  %   �5  !  �5     7  &    7     G7  �   N7     ;8     A8  K   `8  �   �8  3   T9  S   �9     �9  K   �9  1   G:  �   y:     �:     ;     ;     #;  %   (;  t   N;  �   �;     �<     �<     �<     �<     �<  ,   �<     %=  	   B=     L=  �   P=     >  �   &>  0   �>     �>  !   �>     ?  
  ?  F   @  �   X@  E   A  *   bA  Z   �A  +   �A  �   B     �B  P   �B  ?  #C     cD  d   }D     �D  �   �D  �   �E  �   KF     �F  (   �F     �F  	   G  
   G  )   "G     LG     SG     [G     pG     �G     �G     �G     �G     �G         u   &   	   M   r   S   t   c                      Y               =   e      v   J   q   h   @       
   R      W   m   L   3       b   .   $   A           9   H   5   +   d           ]   X   _       "   <   a      7                   B       8   P          x       6         I   >   *   )           f   [   i       g          |   T   ,         G          \      y             j   n   4   z   1             {   }   ?                         :   V      ^            O              D      K       /   !       E      0   %   2   o   -   C   Q   p       Z      N          l   (   w   `      F       k   '   U       #           ;   s     fax detection; requires 'faxdetect=' to be set to 'incoming' or 'both' in  "You have selected Fax Detection on this route. Please select a valid destination to route calls detected as faxes to." %s FAX Migrations Failed %s FAX Migrations Failed, check notification panel for details A4 Address to email faxes to on fax detection.<br />PLEASE NOTE: In this version of FreePBX, you can now set the fax destination from a list of destinations. Extensions/Users can be fax enabled in the user/extension screen and set an email address there. This will create a new destination type that can be selected. To upgrade this option to the full destination list, select YES to Detect Faxes and select a destination. After clicking submit, this route will be upgraded. This Legacy option will no longer be available after the change, it is provided to handle legacy migrations from previous versions of FreePBX only. Adds configurations, options and GUI for inbound faxing Always Generate Detection Code Attachment Format Attempt to detect faxes on this DID. Auto generated migrated user for Fax Both Checking for failed migrations.. Checking if legacy fax needs migrating.. Dahdi Default Fax header Default Local Station Identifier Default Paper Size Detect Faxes Dial System FAX ERROR: No FAX modules detected!<br>Fax-related dialplan will <b>NOT</b> be generated.<br>This module requires Fax for Asterisk (res_fax_digium.so) or spandsp based app_fax (res_fax_spandsp.so) to function. ERROR: No Fax license detected.<br>Fax-related dialplan will <b>NOT</b> be generated!<br>This module has detected that Fax for Asterisk is installed without a license.<br>At least one license is required (it is available for free) and must be installed. Email address Email address that faxes appear to come from if 'system default' has been chosen as the default fax extension. Email address that faxes are sent to when using the "Dial System Fax" feature code. This is also the default email for fax detection in legacy mode, if there are routes still running in this mode that do not have email addresses specified. Enable Fax Enable this user to receive faxes Enabled Enclosed, please find a new fax Enclosed, please find a new fax from: %s Error Correction Mode Error Correction Mode (ECM) option is used to specify whether
			 to use ecm mode or not. Fax Fax Configuration Fax Destination Fax Detection Fax Detection Time Fax Detection Wait Fax Detection type Fax Email Destination Fax Enabled Fax Options Fax Ring Fax drivers supported by this module are: Fax for Asterisk (res_fax_digium.so) with licence Fax user %s Finished Migrating fax users to usermanager For Formats to convert incoming fax files to before emailing. Header information that is passed to remote side of the fax transmission and is printed on top of every page. This usually contains the name of the person or entity sending the fax. How long to wait and try to detect fax How long to wait and try to detect fax. Please note that callers to a Dahdi channel will hear ringing for this amount of time (i.e. the system wont "answer" the call, it will just play ringing). Inbound Fax Destination Change Inbound Fax Detection: %s (%s) Inbound faxes now use User Manager users. Therefore you will need to re-assign all of your destinations that used 'Fax Recipients' to point to User Manager users. You may see broken destinations until this is resolved Inherit Invalid Email for Inbound Fax Legacy Legacy: Same as YES, only you can enter an email address as the destination. This option is ONLY for supporting migrated legacy fax routes. You should upgrade this route by choosing YES, and selecting a valid destination! Letter Maximum transfer rate Maximum transfer rate used during fax rate negotiation. Migrated user %s but unable to set email address to %s because an email [%s] was already set for User Manager User %s Migrating all fax users to usermanager Migrating faxemail field in the fax_users table to allow longer emails... Minimum transfer rate Minimum transfer rate used during fax rate negotiation. Moving simu_fax feature code from core.. NV Fax Detect: Use NV Fax Detection; Requires NV Fax Detect to be installed and recognized by asterisk NVFax New fax from: %s New fax received No No Inbound Routes to migrate No fax detection methods found or no valid license. Faxing cannot be enabled. No: No attempts are made to auto-determine the call type; all calls sent to destination set in the 'General' tab. Use this option if this DID is used exclusively for voice OR fax. On Outgoing Email address Outgoing fax results PDF Received & processed: %s Removing field %s from incoming table.. Removing old globals.. Reset SIP Select the default paper size.<br/>This specifies the size that should be used if the document does not specify a size.<br/> If the document does specify a size that size will be used. Settings Sip: use sip fax detection (t38). Requires asterisk 1.6.2 or greater and 'faxdetect=yes' in the sip config files Spandsp based app_fax (res_fax_spandsp.so) Submit Successfully migrated faxemail field TIFF The following Inbound Routes had FAX processing that failed migration because they were accessing a device with no associated user. They have been disabled and will need to be updated. Click delete icon on the right to remove this notice. The outgoing Fax Machine Identifier. This is usually your fax number. This may be formatted as just 'user@example.com', or 'Fax User <user@example.com>'. The second option will display 'Fax User' in the 'From' field in most email clients. Type of fax detection to use (e.g. SIP or DAHDI) Type of fax detection to use. Unable to migrate %s, because [%s]. Please check your 'Fax Recipients' destinations Updating simu_fax in miscdest table.. User Manager users '%s' have the ability to receive faxes but have no email address defined so they will not be able to receive faxes over email, Via WARNING: Failed migration. Email length is limited to 50 characters. When no fax modules are detected the module will not generate any detection dialplan by default. If the system is being used with phyical FAX devices, hylafax + iaxmodem, or other outside fax setups you can force the dialplan to be generated here. Where to send the faxes Whether to ring while attempting to detect fax. If set to no silence will be heard Yes Yes: try to auto determine the type of call; route to the fax destination if call is a fax, otherwise send to regular destination. Use this option if you receive both voice and fax calls on this line Your maximum transfer rate is set to 2400 in certain circumstances this can break faxing Your minimum transfer rate is set to 2400 in certain circumstances this can break faxing Zaptel all migrations succeeded successfully already done blank done duplicate, removing old from core.. failed migrated migrating defaults.. not needed not present removed starting migration unknown error use  Project-Id-Version: PACKAGE VERSION
Report-Msgid-Bugs-To: 
POT-Creation-Date: 2018-07-19 18:07-0400
PO-Revision-Date: 2016-12-20 01:53+0200
Last-Translator: Alexander <alexander.schley@paranagua.pr.gov.br>
Language-Team: Portuguese (Brazil) <http://weblate.freepbx.org/projects/freepbx/fax/pt_BR/>
Language: pt_BR
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=n != 1;
X-Generator: Weblate 2.4
  detecção de fax; requer que 'faxdetect=' seja definido como 'entrada' ou 'ambos' em  "Você selecionou Detecção de Fax nessa rota. Selecione um destino válido para encaminhar chamadas detectadas como faxes." %s Falha nas Migrações de FAX %s Falha nas Migrações de FAX, verifique o painel de notificações para obter detalhes A4 Endereço para enviar e-mails de fax para a detecção de fax. <br/>AVISO: Nesta versão do FreePBX,você pode definir o destino do fax a partir de uma lista de destinos. Ramais/Usuários podem ser habilitados para fax na tela do usuário/ramal e definir um endereço de e-mail neste local. Isso criará um novo tipo de destino que pode ser selecionado. Para atualizar esta opção para a lista de destinos completos, selecione SIM para Detectar Faxes e selecione um destino. Depois de clicar em enviar, esta rota será atualizada. Esta opção Legado não estará mais disponível após a alteração, ela somente é fornecida para lidar com migrações legadas de versões anteriores do FreePBX. Adiciona configurações, opções e GUI para entradas de fax Sempre Gerar Código de Detecção Formato do Anexo Tentar detectar faxes neste DID. Usuário migrado gerado automaticamente para Fax Ambos Verificando migrações falhadas... Verificar se o fax antigo precisa ser migrado... Dahdi Cabeçalho de Fax Padrão Identificador de Estação Local Padrão Tamanho Padrão do Papel Detecção de Faxes Sistema de Discagem de FAX ERRO: Não há módulos de FAX detectados! <br> O plano de discagem relacionado ao fax <b> NÃO</b> será gerado. <br> Este módulo requer Fax para Asterisk (res_fax_digium.so) ou app_fax baseado em spandsp (res_fax_spandsp.so) para funcionar. ERRO: Nenhuma licença de fax detectada. <br> O plano de discagem relacionado ao fax <b> NÃO </b> pode ser gerado! <br> Este módulo detectou que o Fax para Asterisk está instalado sem uma licença. <br> Pelo menos uma licença é necessária (está disponível gratuitamente) e deve ser instalada. Endereço de e-mail Endereço de e-mail do qual aparecem os faxes se o "padrão do sistema" tiver sido escolhido como ramal de fax padrão. Endereço de e-mail ao qual os faxes são enviados quando usar o código de recurso "Sistema de Discagem de Fax". Este é também o e-mail padrão para a detecção de fax no modo herdado, se houver rotas ainda em execução neste modo que não tenham endereços de e-mail especificados. Habilitar Fax Permitir que este usuário receba faxes Habilitado Incluso, por favor, encontre um novo fax Fechado, chegou um novo fax de: %s Modo de Correção de Erros A opção ECM (Modo de Correcção de Erros) é utilizada para para 
			usar ou não o modo ecm. Fax Configuração de Fax Destino de Fax Detecção de Fax Tempo de Detecção de Fax Espera de Detecção de Fax Tipo de Detecção de Fax Destino de E-mail de Fax Fax Habilitado Opções de Fax Toque do Fax Os drivers de fax suportados por este módulo são: Fax para Asterisk (res_fax_digium.so) com licença Usuário de Fax %s Finalizada a migração de usuários de fax para usermanager Para Formatos para converter arquivos de fax recebidos antes de enviar e-mails. Informações de cabeçalho que são passadas para o lado remoto da transmissão de fax e são impressas em cima de cada página. Geralmente contém o nome da pessoa ou entidade que envia o fax. Quanto tempo para esperar e tentar detectar fax Quanto tempo para esperar e tentar detectar fax. Observe que os usuários chamadores de um canal Dahdi ouvirão toques durante este período de tempo (isto é, o sistema não responderá a chamada, apenas tocará). Mudança de Destino do Fax de Entrada Detecção de Fax de Entrada: %s (%s) Os faxes de entrada agora usam os usuários do Gerenciador de Usuários. Portanto, você precisará reatribuir todos os destinos que usaram "Destinatários de Fax" para apontar para usuários do Gerenciador de Usuários. Você poderá ver os destinos quebrados até que isso seja resolvido Herdar E-mail inválido para o Fax de Entrada Legado Legado: O mesmo que SIM, somente você pode inserir um endereço de e-mail como destino. Esta opção é SOMENTE para apoiar rotas migradas de fax legados. Você deve atualizar esta rota escolhendo SIM e selecionando um destino válido! Carta Taxa de transferência máxima Taxa de transferência máxima usada durante a negociação de taxa de fax. Usuário %s migrado, mas não conseguiu definir o endereço de e-mail para %s porque um e-mail [%s] já estava configurado para Usuário do Gerenciador de Usuários %s Migrando todos os usuários de fax para usermanager Migrando o campo faxemail na tabela fax_users para permitir e-mails mais longos ... Taxa mínima de transferência Taxa de transferência mínima usada durante a negociação de taxa de fax. Movendo simu_fax código de recurso do núcleo .. Detecção de Fax NV: Utilizar Detecção de Fax NV; Requer que a Detecção de Fax NV seja instalada e reconhecida pelo asterisk FaxNV Novo fax de: %s Novo fax recebido Não Não há Rotas de Entrada para migrar Nenhum método de detecção de fax encontrado ou nenhuma licença válida. O envio de fax não pode ser habilitado. Não: Não são feitas tentativas para auto-determinar o tipo de chamada; Todas as chamadas enviadas para destino definidas na guia 'Geral'. Use esta opção se este o DID for usado exclusivamente para voz OU fax. Ligado Endereço de E-mail de Saída Resultados de fax de saída PDF Recebido e processado: %s Removendo o campo %s da tabela de entrada .. Removendo globais antigos... Reiniciar SIP Selecione o tamanho de papel padrão. <br/> Especifica o tamanho que deve ser usado se o documento não especificar um tamanho. <br/> Se o documento não especificar um tamanho que será utilizado. Configurações Sip: use a detecção de fax sip (t38). Requer asterisk 1.6.2 ou superior e 'faxdetect = yes' nos arquivos de configuração sip Baseado em Spandsp  app_fax (res_fax_spandsp.so) Enviar Campo faxemail migrado com êxito TIFF As seguintes rotas de entrada do processamento de fax falharam a migração porque estavam acessando um dispositivo sem nenhum usuário associado. Eles foram desativados e precisarão ser atualizados. Clique no ícone de exclusão à direita para remover este aviso. O identificador de fax de saída. Normalmente é o seu número de fax. Isso pode ser formatado como apenas 'usuario@exemplo.com' ou 'Usuário do Fax <usuario@exemplo.com>'. A segunda opção exibirá 'Usuário do Fax' no campo 'De' na maioria dos clientes de e-mail. Tipo de detecção de fax a ser utilizado (por exemplo, SIP ou DAHDI) Tipo de detecção de fax a ser utilizado. Não é possível migrar %s, porque [%s]. Verifique os destinos de "Destinatários de Fax" Atualizando simu_fax na tabela miscdest ... Usuários do Gerenciador de Usuários '%s' têm a capacidade de receber faxes, mas não têm nenhum endereço de e-mail definido, portanto eles não conseguirão receber fax por e-mail, Via AVISO: Migração com falha. O comprimento do email é limitado a 50 caracteres. Quando não forem detectados módulos de fax, o módulo não irá gerar nenhuma detecção de plano de discagem por predefinição. Se o sistema estiver sendo usado com dispositivos físicos de FAX, hylafax + iaxmodem, ou outras configurações externas de fax, você pode forçar o plano de discagem a ser gerado aqui. Para onde enviar os faxes Se deseja que toque durante a tentativa de detectar fax. Se definido para não, ficará em silêncio Sim Sim: tentar determinar automaticamente o tipo de chamada; envia para o destino de fax se a chamada for um fax, caso contrário enviar para o destino regular. Utilize esta opção se receber chamadas de voz e de fax nesta linha A sua taxa de transferência máxima está definida para 2400, em determinadas circunstâncias, isto pode romper o envio de faxes A sua taxa de transferência mínima está definida para 2400, em determinadas circunstâncias, isto pode romper o serviço de fax Zaptel todas as migrações foram bem-sucedidas já concluído em branco concluído duplicado, removendo antigo do núcleo .. falhou migrado migrando padrões... não é necessário não está presente removido iniciando migração erro desconhecido utiliza  