Þ    1      ¤  C   ,      8  K   9  w     k  ý  7   i     ¡  $   À     å     ë      þ          ,  ý   <  n   :	  ï   ©	  !   
     »
     Ã
  Y   Ù
     3     7     I     Y     l               ¡  µ   ­     c       Ý        g  7   }     µ  7   Ë  f        j     p  M   s     Á  p   Ê     ;  E   B       ÷   ¦       Ç   ¢     j     q  ®  v  d   %  ª       5  H   ¸       3         T     Z  !   y          ¨    Ä     Y  Z  ç  9   B     |       o          	     	        $     4     G     \     o  Ã     (   G     p    }     
   N          o   N         Ô      e!  	   k!  f   u!     Ü!     ã!     t"  5   {"  !   ±"  w  Ó"     K$  !  R$     t%     {%     +                #       *   /   	                       !   -   &   
                                   "                   %   )                                                       $   1         0   '       .             (   ,        fax detection; requires 'faxdetect=' to be set to 'incoming' or 'both' in  "You have selected Fax Detection on this route. Please select a valid destination to route calls detected as faxes to." Address to email faxes to on fax detection.<br />PLEASE NOTE: In this version of FreePBX, you can now set the fax destination from a list of destinations. Extensions/Users can be fax enabled in the user/extension screen and set an email address there. This will create a new destination type that can be selected. To upgrade this option to the full destination list, select YES to Detect Faxes and select a destination. After clicking submit, this route will be upgraded. This Legacy option will no longer be available after the change, it is provided to handle legacy migrations from previous versions of FreePBX only. Adds configurations, options and GUI for inbound faxing Always Generate Detection Code Attempt to detect faxes on this DID. Dahdi Default Fax header Default Local Station Identifier Detect Faxes Dial System FAX ERROR: No Fax license detected.<br>Fax-related dialplan will <b>NOT</b> be generated!<br>This module has detected that Fax for Asterisk is installed without a license.<br>At least one license is required (it is available for free) and must be installed. Email address that faxes appear to come from if 'system default' has been chosen as the default fax extension. Email address that faxes are sent to when using the "Dial System Fax" feature code. This is also the default email for fax detection in legacy mode, if there are routes still running in this mode that do not have email addresses specified. Enable this user to receive faxes Enabled Error Correction Mode Error Correction Mode (ECM) option is used to specify whether
			 to use ecm mode or not. Fax Fax Configuration Fax Destination Fax Detection Time Fax Detection type Fax Email Destination Fax Options Fax user %s Header information that is passed to remote side of the fax transmission and is printed on top of every page. This usually contains the name of the person or entity sending the fax. Inbound Fax Detection: %s (%s) Legacy Legacy: Same as YES, only you can enter an email address as the destination. This option is ONLY for supporting migrated legacy fax routes. You should upgrade this route by choosing YES, and selecting a valid destination! Maximum transfer rate Maximum transfer rate used during fax rate negotiation. Minimum transfer rate Minimum transfer rate used during fax rate negotiation. NV Fax Detect: Use NV Fax Detection; Requires NV Fax Detect to be installed and recognized by asterisk NVFax No No fax detection methods found or no valid license. Faxing cannot be enabled. Settings Sip: use sip fax detection (t38). Requires asterisk 1.6.2 or greater and 'faxdetect=yes' in the sip config files Submit The outgoing Fax Machine Identifier. This is usually your fax number. Type of fax detection to use. When no fax modules are detected the module will not generate any detection dialplan by default. If the system is being used with phyical FAX devices, hylafax + iaxmodem, or other outside fax setups you can force the dialplan to be generated here. Yes Yes: try to auto determine the type of call; route to the fax destination if call is a fax, otherwise send to regular destination. Use this option if you receive both voice and fax calls on this line Zaptel use  Project-Id-Version: FreePBX 2.10.0.6
Report-Msgid-Bugs-To: 
POT-Creation-Date: 2018-09-04 23:23-0400
PO-Revision-Date: 2014-02-25 03:44+0200
Last-Translator: Chise Mishima <c.mishima@qloog.com>
Language-Team: Japanese <http://192.168.30.85/projects/freepbx/fax/ja/>
Language: ja
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=1; plural=0;
X-Generator: Weblate 1.8
  fax æ¤ç¥; 'faxdetect=' ã 'incoming' ãããã¯ 'both' ã«è¨­å®ããå¿è¦ãããã¾ãã "ããªãã¯ãã®çµè·¯ã§Faxæ¤ç¥ãé¸æãã¾ãããfaxã¨ãã¦æ¤ç¥ãããã³ã¼ã«ãã«ã¼ãã£ã³ã°ããæå¹ãªå®åãé¸æãã¦ãã ããã" faxæ¤ç¥ã«ããã¦faxãEã¡ã¼ã«éä¿¡ããå¯¾è±¡ã¢ãã¬ã¹ã<br />æ³¨æ: ãã®ãã¼ã¸ã§ã³ã®FreePBXã§ã¯ãããã§å®åãªã¹ãããfaxã®å®åãè¨­å®ãããã¨ãå¯è½ã§ããåç·/ã¦ã¼ã¶ã¼ ã¯ãã¦ã¼ã¶ã¼/åç· ã¹ã¯ãªã¼ã³åã§faxåä¿¡ãæå¹ã«ãããã¨ãå¯è½ã§ãããã§Eã¡ã¼ã«ã¢ãã¬ã¹ãè¨­å®ãããã¨ãã§ãã¾ããããã§ã¯æ°ãã«é¸æå¯è½ãªå®åã¿ã¤ããä½æãã¾ãããã®ãªãã·ã§ã³ãå¨å®åãªã¹ãã«ã¢ããã°ã¬ã¼ãããã«ã¯ããFaxãæ¤ç¥ãã§ãã¯ãããé¸æãã¦ãå®åãé¸æãã¾ãããéä¿¡ããæ¼ããå¾ã«ãã®ã«ã¼ãã¯ã¢ããã°ã¬ã¼ãããã¾ãããã®ã¬ã¬ã·ã¼ãªãã·ã§ã³ã¯å¤æ´å¾ã¯å©ç¨ã§ãã¾ãããåã®ãã¼ã¸ã§ã³ã®FreePBXããã®ã¬ã¬ã·ã¼ç§»è¡ããµãã¼ãããããã«ã ãæä¾ããã¦ãã¾ãã ã¤ã³ãã¦ã³ãFaxã®ããã®ãªãã·ã§ã³ã¨GUIã®è¨­å®ã®è¿½å  å¸¸ã«æ¤ç¥ã³ã¼ããçæ ãã®ãã¤ã¤ã«ã¤ã³ã§faxæ¤ç¥ãè©¦ã¿ãã Dahdi ããã©ã«ãFaxãããã¼ ããã©ã«ãã®éä¿¡å´FAX ID Faxãæ¤ç¥ ãã¤ã¤ã«ã·ã¹ãã FAX ERRORï¼ Faxã®ã©ã¤ã»ã³ã¹ãæ¤ç¥ã§ãã¾ããã§ããã<br>Faxã«é¢é£ãããã¤ã¤ã«ãã©ã³ã<b>çæããã¾ããã</b><br>ãã®ã¢ã¸ã¥ã¼ã«ã§ãã©ã¤ã»ã³ã¹ãªãã®Fax for Asteriskãã¤ã³ã¹ãã¼ã«ããã¦ãããã¨ãæ¤ç¥ãã¾ããã<br>å°ãªãã¨ã1ã©ã¤ã»ã³ã¹(ç¡æã§å©ç¨ã§ãã¾ã)ãã¤ã³ã¹ãã¼ã«ããã¦ããå¿è¦ãããã¾ãã ããã©ã«ãfaxåç·ã¨ãã¦ã'ã·ã¹ãã ããã©ã«ã'ãé¸æãããå ´åã«faxæå ±ãéä¿¡ããEã¡ã¼ã«ã¢ãã¬ã¹ã "ãã¤ã¤ã«ã·ã¹ãã ãã¡ãã¯ã¹"ã­ã¼ã³ã¼ããä½¿ç¨ãã¦ããã¨ãã«FAXãéä¿¡ãããã¡ã¼ã«ã¢ãã¬ã¹ãã¬ã¬ã·ã¼ã¢ã¼ãã§èµ·åãã¦ãã¦ãEã¡ã¼ã«ã¢ãã¬ã¹ãæå®ããã¦ããªãã«ã¼ããã¾ã ããå ´åã«ã¯ãfaxæ¤ç¥ã®ããã®ããã©ã«ãã®Eã¡ã¼ã«ã¢ãã¬ã¹ã«ããªãã¾ãã ãã®ã¦ã¼ã¶ã¼ãfaxãåä¿¡ã§ããããã«ãã æå¹ ã¨ã©ã¼è¨æ­£ã¢ã¼ã ã¨ã©ã¼è»¢éã¢ã¼ã(ECM)ãªãã·ã§ã³ã¯æ¬¡ãæå®ããçºã«ä½¿ç¨
			ecmã¢ã¼ãã®ä½¿ç¨æç¡ã Fax Faxè¨­å® Faxå®å Faxæ¤ç¥æé Faxæ¤ç¥ã¿ã¤ã Fax Eã¡ã¼ã«å®å Faxãªãã·ã§ã³ Fax ã¦ã¼ã¶ã¼ %s faxè»¢éã®ãªã¢ã¼ãå´ã«éåºãããå¨ã¦ã®ãã¼ã¸ã®ãããã«ããªã³ãããããããã¼æå ±ãéå¸¸faxãéä¿¡ããäººã®ååãå®ä½ã®ååãå«ã¾ãã¾ãã ã¤ã³ãã¦ã³ã Fax æ¤ç¥ï¼ %s (%s) ã¬ã¬ã·ã¼ ã¬ã¬ã·â: ãã¯ããã¨åãã§ãããå®åã¨ãã¦Eã¡ã¼ã«ã¢ãã¬ã¹ã®ã¿ãå¥åãããã¨ãã§ãã¾ãããã®ãªãã·ã§ã³ã¯ãç§»è¡ããå¾æ¥ã®ãã¡ã¯ã¹ã«ã¼ãããµãã¼ãããããã®ã¿ã§ä½¿ç¨ããã¾ãããã¯ãããé¸æããæå¹ãªå®åãé¸æãããã¨ã«ããããã®ã«ã¼ããã¢ããã°ã¬ã¼ãããå¿è¦ãããã¾ã æå¤§è»¢éã¬ã¼ã faxã¬ã¼ããã´ã·ã¨ã¼ã·ã§ã³ä¸­ã«ä½¿ç¨ãããæå¤§è»¢éã¬ã¼ã æå°è»¢éã¬ã¼ã faxã¬ã¼ããã´ã·ã¨ã¼ã·ã§ã³ä¸­ã«ä½¿ç¨ãããæå°è»¢éã¬ã¼ã NV Faxæ¤ç¥: NV Faxæ¤ç¥ãä½¿ç¨ãã¾ã; NV Faxæ¤ç¥ãã¤ã³ã¹ãã¼ã«ãããasteriskã«èªè­ããã¦ããå¿è¦ãããã¾ãã NVFax ããã faxæ¤ç¥æ¹æ³åã¯æå¹ãªã©ã¤ã»ã³ã¹ãè¦ã¤ããã¾ãããFaxãæå¹ã«ãªãã¾ãã è¨­å® Sip: sip faxæ¤ç¥(t38)ãä½¿ç¨ãã¾ããasterisk 1.6.2ä»¥éã¨ãsipè¨­å®ãã¡ã¤ã«ã§ 'faxdetect=yes' ã¨ããå¿è¦ãããã¾ãã éä¿¡ éä¿¡ããFAXã®IDãéå¸¸ããªãã®faxçªå·ã ä½¿ç¨ãããfaxæ¤ç¥ã¿ã¤ã faxã¢ã¸ã¥ã¼ã«ãæ¤ç¥ãããªãã£ãå ´åãã¢ã¸ã¥ã¼ã«ã¯ããã©ã«ãã§æ¤ç¥ãã¤ã¤ã«ãã©ã³ãçæãã¾ãããããã·ã¹ãã ãç©ççãªFAXããã¤ã¹ãããhylafax + iaxmodemããä»ã®å¤é¨faxè¨­å®ã¨ä¸ç·ã«ä½¿ç¨ããã¦ããå ´åãããã§çæããããã¤ã¤ã«ãã©ã³ãå¼·å¶å®è¡ãããã¨ãã§ãã¾ãã ã¯ã ã¯ã: ã³ã¼ã«ã¿ã¤ãã®èªåæ±ºå®ãè©¦ã¿ã¾ã; ã³ã¼ã«ãfaxã®å ´åã¯faxå®åã«ã«ã¼ãã£ã³ã°ããããä»¥å¤ã®å ´åã¯éå¸¸ã®å®åã«éä¿¡ãã¾ãããã®åç·ä¸ã®ã³ã¼ã«ã§é³å£°ãfaxãåä¿¡ããå ´åã«ãã®ãªãã·ã§ã³ãä½¿ç¨ãã¾ãã Zaptel ä½¿ç¨ 