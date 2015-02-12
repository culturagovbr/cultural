<?php
class MapasCulturaisConfiguration {
    const NAME = "Mapas Culturais";

    protected static $nameClass;
    protected static $nameGroup;
    protected static $options;
    protected static $widgetsName;

    static function init() {
        self::$nameClass = strtolower(__CLASS__);
        self::$nameGroup = strtolower(__CLASS__) . 'group';

        add_action( 'admin_init', array( __CLASS__, 'optionsInit' ) );
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );

        //wp_enqueue_script( 'mapasculturais-configuration', get_template_directory_uri() . '/js/mapasculturais-configuration.js', array('jquery'), '', true );
        //wp_enqueue_script( 'mapasculturais-configuration', 'https://raw.githubusercontent.com/ehynds/jquery-ui-multiselect-widget/1.13/src/jquery.multiselect.js', array('jquery'), '', true );


    }

    static function optionsInit() {
        register_setting( self::$nameGroup, self::$nameClass, array( __CLASS__, 'optionsValidation') );
    }

    static function menu() {
        add_menu_page (
            self::NAME,
            self::NAME,
            'manage_options',
            self::$nameClass,
            array( __CLASS__, 'callbackPage' )
        );
    }

    static function optionsValidation($input) {
        return $input;
    }

    static function callbackPage() {

        define('API_URL', 'http://spcultura.prefeitura.sp.gov.br/api/');

        if(DCache::exists('API', 'configs', 60 * 60)){

            _pr('PEGOU DO CACHE ' . date('h:i:s'));
            $configs = DCache::get('API', 'configs');

        }else{

            $linguagens = json_decode(wp_remote_get(API_URL . 'term/list/linguagem', ['timeout'=>'120'])['body']);
            $geoDivisions = json_decode(wp_remote_get(API_URL . 'geoDivision/list/includeData:1', ['timeout'=>'120'])['body']);
            $eventDescription = json_decode(wp_remote_get(API_URL . 'event/describe', ['timeout'=>'120'])['body']);
            $agents = json_decode(wp_remote_get(API_URL . 'agent/find/?@select=id,singleUrl,name,type,shortDescription,terms&@files=(avatar.avatarSmall):url&@order=name%20ASC', ['timeout'=>'120'])['body']);
            $spaces = json_decode(wp_remote_get(API_URL . 'space/find/?@select=id,singleUrl,name,type,shortDescription,terms,endereco&@files=(avatar.avatarSmall):url&@order=name%20ASC', ['timeout'=>'120'])['body']);
            $projects = json_decode(wp_remote_get(API_URL . 'project/find/?@select=id,singleUrl,name,type,shortDescription,terms&@files=(avatar.avatarSmall):url&@order=name%20ASC', ['timeout'=>'120'])['body']);

            $configs = [
               'linguagens' => (object) ['order' => 0, 'key' => 'linguagens', 'label' => 'Linguagens', 'data' => [] ],
               'classificacaoEtaria' => (object) ['order' => 1, 'key' => 'classificacaoEtaria', 'label' => 'Classificação Etária', 'data' => [] ],
               'geoDivisions' => (object) ['order' => 2, 'key' => 'classificacaoEtaria', 'label' => 'Divisões Geográficas:', 'data' => [], 'type' => 'header' ],
               'agents' => (object) ['order' => count($geoDivisions)+3+1, 'key' => 'agents', 'label' => 'Agentes', 'data' => $agents, 'type' => 'entity' ],
               'spaces' => (object) ['order' => count($geoDivisions)+3+2, 'key' => 'spaces', 'label' => 'Espaços', 'data' => $spaces, 'type' => 'entity'],
               'projects' => (object) ['order' => count($geoDivisions)+3+3, 'key' => 'projects', 'label' => 'Projetos', 'data' => $projects, 'type' => 'entity']
            ];

            $configs['linguagens']->data = $linguagens;
            $configs['classificacaoEtaria']->data = array_values((array) $eventDescription->classificacaoEtaria->options);

            $i=0;
            foreach($geoDivisions as $geoDivision){
                $i++;
                $configs[$geoDivision->metakey] = (object) ['order' => $configs['geoDivisions']->order+$i,'key' => $geoDivision->metakey, 'label' => $geoDivision->name, 'data' => $geoDivision->data];
            }

            usort($configs, function($a, $b){
                return $a->order > $b->order;
            });

            DCache::set('API', 'configs', $configs);
        }

        //_pr($configs);

        ?>
        <style>
        .thumb {
            width: 72px;
            height: 72px;
            background-color:#ccc;
            margin-right: 5px;
        }
        </style>
        <div class="wrap span-20">
            <h2><?php echo __('Configuração dos Mapas Culturais', 'cultural'); ?></h2>

            <form action="options.php" method="post" class="clear prepend-top">
                <?php settings_fields('theme_options_options'); ?>
                <?php
                    $options = wp_parse_args(get_option('theme_options'), get_theme_default_options());
                    $selfOptions = $options[self::$nameClass];
                ?>

                <div class="span-20 ">

                    <?php //////////// Edite a partir daqui //////////  ?>

                    <h3><?php _e("Configuração da API de Eventos", 'cultural'); ?></h3>

                    <p class="textright clear prepend-top">
                        <input type="submit" class="button-primary" value="<?php _e('Salvar', 'cultural'); ?>" />
                    </p>

                    <div class="span-6 last">
                        <label>
                            <strong>Palavra-Chave</strong> <br>
                            <input type="text" name="<?php echo 'theme_options[' . self::$nameClass . '][keyword]'; ?>"  value="<?php echo htmlspecialchars($selfOptions['keyword']); ?>" style="width:80%">
                        </label>
                        <br><br>
                        <label>
                            <input type="checkbox" name="<?php echo 'theme_options[' . self::$nameClass . '][verified]'; ?>"  <?php if($selfOptions['verified']) echo 'checked'; ?>>
                            <strong>Somente Eventos Verificados com Selo</strong>
                        </label>
                        <br><br>
                        <?php foreach($configs as $c):
                            $metaName = 'theme_options[' . self::$nameClass . '][' . $c->key . ']';
                            $metaValue = $selfOptions[$c->key]; ?>

                            <?php if($c->type === 'entity') echo '<h1>'; else echo '<strong>';  ?>
                                <?php _e($c->label, "cultural"); ?>
                            <?php if($c->type === 'entity') echo '</h1>'; else echo '</strong>';  ?>
                            <br>
                            <?php switch($c->type):
                                      case 'heading': ?>
                                    <br>
                                    <?php break; ?>
                                <?php case 'entity': ?>
                                    <?php foreach($c->data as $entity): ?>
                                        <label>
                                            <a href="<?php echo $entity->singleUrl; ?>" target="_blank">
                                                <?php
                                                if(!empty($entity->{'@files:avatar.avatarSmall'})){
                                                    $avatarUrl = $entity->{'@files:avatar.avatarSmall'}->url;
                                                }else{
                                                    $avatarUrl = API_URL . '../assets/img/avatar--' . substr($c->key, 0, -1) . '.png';
                                                }
                                                ?>
                                                <img class="thumb" src="<?php echo $avatarUrl; ?>" align="left" alt="Ver Página">
                                            </a>

                                            <input type="checkbox" name="<?php echo "{$metaName}[{$entity->id}]"; ?>"  <?php if($metaValue[$entity->id]) echo 'checked'; ?> value="<?php echo htmlspecialchars(json_encode($entity)); ?>">

                                            <strong><?php echo $entity->name; ?></strong>
                                            <?php if($entity->endereco):?>
                                                - <?php echo $entity->endereco; ?>
                                            <?php endif; ?>
                                            <br>Tipo: <?php echo $entity->type->name; ?>
                                            <br>
                                            <?php if(!empty($entity->terms->area)):?>
                                                Área(s) de atuação: <?php echo implode(', ', $entity->terms->area); ?>
                                            <?php endif; ?>
                                            <br>
                                            <?php if(!empty($entity->terms->tag)):?>
                                                Tags: <?php echo implode(', ', $entity->terms->tag); ?>
                                            <?php endif; ?>
                                        </label>
                                        <br>
                                        <br>
                                    <?php endforeach; ?>
                                    <br>
                                    <?php break; ?>
                                <?php default: ?>
                                    <?php foreach($c->data as $d): ?>
                                        <label>
                                            <input type="checkbox" name="<?php echo "{$metaName}[{$d}]"; ?>"  <?php if($metaValue[$d]) echo 'checked'; ?> >
                                            <?php echo $d; ?>
                                        </label>
                                        <br>
                                    <?php endforeach; ?>
                                    <br>
                                    <?php break; ?>
                            <?php endswitch; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php ///// Edite daqui pra cima ////  ?>

                </div>

                <p class="textright clear prepend-top">
                    <input type="submit" class="button-primary" value="<?php _e('Salvar', 'cultural'); ?>" />
                </p>
            </form>
         </div>
        <?php
    }
}
MapasCulturaisConfiguration::init();