��    4      �  G   \      x  K   y  w   �  k  =  7   �     �        $        7     =      P     q     ~  �   �  �   \	  n   Z
  �   �
  !   �     �     �  Y   �     S     W     i     y     �     �     �     �  9   �  �        �     �  �   �     �  7   �       7   %  f   ]     �     �  M   �       p   $     �  E   �     �  �         �  �   �     �     �  �  �  �   �  �   �  �  y  p   ;  G   �     �  X        k  @   q  H   �  %   �  *   !  �  L  �  �  �   �   �  �!  T   y#     �#  3   �#  y   $     �$  #   �$     �$  9   �$  2   %  /   G%     w%  $   �%  �   �%  f  \&  ;   �'     �'  �  (  :   �)  {   *  8   �*  y   �*  �   6+     ,     %,  �   ,,     �,  �   -     �-  u   �-  [   t.     �.     �0  �  �0     �2     �2         &          3          .       )   (               /      !          %           #                               1   ,      
   2                    4      "                        -             *      	                        0   +         '   $     fax detection; requires 'faxdetect=' to be set to 'incoming' or 'both' in  "You have selected Fax Detection on this route. Please select a valid destination to route calls detected as faxes to." Address to email faxes to on fax detection.<br />PLEASE NOTE: In this version of FreePBX, you can now set the fax destination from a list of destinations. Extensions/Users can be fax enabled in the user/extension screen and set an email address there. This will create a new destination type that can be selected. To upgrade this option to the full destination list, select YES to Detect Faxes and select a destination. After clicking submit, this route will be upgraded. This Legacy option will no longer be available after the change, it is provided to handle legacy migrations from previous versions of FreePBX only. Adds configurations, options and GUI for inbound faxing Always Generate Detection Code Attachment Format Attempt to detect faxes on this DID. Dahdi Default Fax header Default Local Station Identifier Detect Faxes Dial System FAX ERROR: No FAX modules detected!<br>Fax-related dialplan will <b>NOT</b> be generated.<br>This module requires Fax for Asterisk (res_fax_digium.so) or spandsp based app_fax (res_fax_spandsp.so) to function. ERROR: No Fax license detected.<br>Fax-related dialplan will <b>NOT</b> be generated!<br>This module has detected that Fax for Asterisk is installed without a license.<br>At least one license is required (it is available for free) and must be installed. Email address that faxes appear to come from if 'system default' has been chosen as the default fax extension. Email address that faxes are sent to when using the "Dial System Fax" feature code. This is also the default email for fax detection in legacy mode, if there are routes still running in this mode that do not have email addresses specified. Enable this user to receive faxes Enabled Error Correction Mode Error Correction Mode (ECM) option is used to specify whether
			 to use ecm mode or not. Fax Fax Configuration Fax Destination Fax Detection Time Fax Detection type Fax Email Destination Fax Options Fax user %s Formats to convert incoming fax files to before emailing. Header information that is passed to remote side of the fax transmission and is printed on top of every page. This usually contains the name of the person or entity sending the fax. Inbound Fax Detection: %s (%s) Legacy Legacy: Same as YES, only you can enter an email address as the destination. This option is ONLY for supporting migrated legacy fax routes. You should upgrade this route by choosing YES, and selecting a valid destination! Maximum transfer rate Maximum transfer rate used during fax rate negotiation. Minimum transfer rate Minimum transfer rate used during fax rate negotiation. NV Fax Detect: Use NV Fax Detection; Requires NV Fax Detect to be installed and recognized by asterisk NVFax No No fax detection methods found or no valid license. Faxing cannot be enabled. Settings Sip: use sip fax detection (t38). Requires asterisk 1.6.2 or greater and 'faxdetect=yes' in the sip config files Submit The outgoing Fax Machine Identifier. This is usually your fax number. Type of fax detection to use. When no fax modules are detected the module will not generate any detection dialplan by default. If the system is being used with phyical FAX devices, hylafax + iaxmodem, or other outside fax setups you can force the dialplan to be generated here. Yes Yes: try to auto determine the type of call; route to the fax destination if call is a fax, otherwise send to regular destination. Use this option if you receive both voice and fax calls on this line Zaptel use  Project-Id-Version: 1.3
Report-Msgid-Bugs-To: 
POT-Creation-Date: 2018-09-05 22:44-0400
PO-Revision-Date: 2015-05-01 23:11+0200
Last-Translator: Yuriy <alliancesko@gmail.com>
Language-Team: Russian <http://weblate.freepbx.org/projects/freepbx/fax/ru_RU/>
Language: ru_RU
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=3; plural=n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2;
X-Generator: Weblate 2.2-dev
  детектирование факса; требуется установить параметр 'faxdetect=' в 'incoming' (входящие) или 'both' (оба направления) в  "Вы выбрали детектирование факсов на этом маршруте. Укажите назначение, куда будут направляться звонки, определённые как факсы." Адрес эл. почты для распознанных и принятых факсов.<br />ЗАМЕЧАНИЕ: в этой версии FreePBX можно указать назначение для факса из списка всех назначений. Для внутренних номеров/пользователей использование факса доступно в модуле Внутренние номера, где указывается адрес их эл. почты. Это создаёт новый тип назначения, который может быть указан в дальнейшем.Чтобы обновить эту опцию до полного списка назначений выберите ДА в разделе Детектировать факсы и укажите назначение. После подтверждения изменений в вэб интерфейсе этот маршрут будет обновлён. Эта устаревшая опция не будет больше доступна после изменений. Она служит только для миграции с предыдущих версий FreePBX. Добавить конфигурацию, опции и интерфейс для входящих факсов Всегда генерировать код распознавания Формат вложения Попытка детектировать факсы на этом входящем DID. DAHDI Заголовок факсимильного сообщения Идентификатор местонахождения станции Детектировать факсы Набрать системный факс ОШИБКА: Не найдено модулей для факса! План набора, связанный с факсом генерироваться <b>НЕ</b> будет. Этот модуль для работы требует факсовых модулей для Астериска - res_fax_digium.so или основанных на spandsp app_fax (res_fax_spandsp.so). ОШИБКА: Не обнаружено лицензии на ФАКС.<br>Диал-план для факса <b>НЕ</b> сгенерирован.<br>Модуль обнаружил проинсталлированую аппликацию Fax for Asterisk без лицензии.<br>По крайней мере одна лицензия должна быть установлена (одну можно получить бесплатно). Адрес электронной почты, от которого посылается сообщение с факсом, если указано 'системный (по умолчанию)' в качестве назначения для факса. Адрес эл. почты куда будут посылаться факсы, если набран сервисный код "Набрать системный факс". Это также адрес эл. почты по умолчанию для устаревшего режима детектирования факса, если маршрут по прежнему использует этот режим и не указан никакой другой адрес эл. почты. Разрешить этому пользователю принимать факсы Включено Режим коррекции ошибок (ЕСМ) Указывается - использовать режим коррекции ошибок (ЕСМ)
				 или нет. Факс Конфигурация факса Назначение факса Время для детектирования факса Метод детектирования факса Адрес эл. почты для факсов Опции факса Пользватель факса %s Форматы для преобразования входящих факсимильных файлов для отправки по электронной почте. Заголовок факсимильного сообщения передаётся на принимающий факс и выпечатывается сверху на каждой странице. Обычно он содержит имя персоны или компании, передающий это факсимильное сообщение. Распознавать входящий факс: %s (%s) Устаревший Устаревший: тоже, что и ДА, но требуется указать только адрес эл. почты в качестве назначения. Эта опция служит ТОЛЬКО для поддержки устаревших маршрутов для факсов. Нужно обновить этот маршрут указав ДА и выбрать действительное назначение! Максимальная скорость передачи Максимальная скорость передачи для распознавания скорости факсов. Минимальная скорость передачи Минимальная скорость передачи для распознавания скорости факсов. NV Fax Detect: Использовать метод детектирования NV Fax; требуется инсталлировать NV Fax Detect дополнительно, чтобы он распознавался в Asterisk NVFAX Нет Не найдено метода определения факса в текущей лицензии. Факсимильные сообщения не могут быть задействованы. Настройки Sip: использовать распознавание факса в канале Sip (t38). Требуется asterisk 1.6.2 или выше и опция 'faxdetect=yes' в sip конфигурационных файлах Сохранить Идентификатор факс аппарата. Обычно это просто тел. номер факса. Какой метод детектирования будет использоваться. Если в системе нет никакого факс-приложения, то модуль не генерирует распознавания диалплана по умолчанию. Если система использует, например, физические факс-аппараты, или связку hylafax + iaxmodem, или какое-то другое стороннее факс-решение, можно форсировать распознавание в диалплане. Да Да: попытка детектировать входящий звонок; если будет распознан как факс-звонок, то он будет направлен по назначению для факсов. В противном случае звонок будет обработан обычным образом. Используйте эту опцию если получаете и голосовые, и факсовые входящие вызовы на этой линии ZAPTEL использовать  