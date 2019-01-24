<?php 

	/**
		Plugin Name: Quake
		Description: plugin that receive earthquake info from quake.local and show in main page
		Version: 0.0.1
		Author: Gaetano Campanella
	*/

	class Quake{
		//inizializzazioni varie
		public function __construct(){
			add_action('admin_menu', array($this, 'addAdminMenu'));
			add_action('init', array($this, 'addQuakeCategory'));
			add_action('init', array($this, 'quake_post'));
			wp_register_style('prefix_bootstrap', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css');
			wp_enqueue_style('prefix_bootstrap');

			global $wpdb;
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

			add_action( 'manage_quake_posts_columns', array($this, 'set_custom_edit_book_columns' ));
            add_action( 'manage_quake_posts_custom_column', array($this, 'custom_quake_column'), 10, 2);
		}
		//elenco delle colonne custom che voglio mostrare
		public function set_custom_edit_book_columns($columns){
            $col = [
            	'creation_time' => 'Data Evento'
                ,'magnitude' => 'Magnitudo'
                ,'location' => 'Luogo'
            ];
            return array_merge($columns, $col);
        }
        //per ogni colonna custom specifico come valorizzarle
        public  function custom_quake_column($column, $post_id){
            $meta = get_post_meta($post_id);
            switch($column){
                case 'magnitude':
                    echo $meta['magnitude'][0];                    
                break;
                case 'creation_time':
                    echo date('d/m/Y H:i', strtotime($meta['creation_time'][0]));
                break;
                case 'location':
                    echo $meta['location'][0];                    
                break;
            }
        }

        //quando eseguo attiva/disattiva del plugin aggiorno la struttura del DB
		public function db_update(){
			$sql = "
				CREATE TABLE earthquake_config (
					id integer auto_increment,
					min_magnitude integer not null,
					max_magnitude integer not null,
					primary key(id)
				)
			";

			$sql_b = "
				CREATE TABLE earthquake (
					id integer auto_increment,
					id_earthquake varchar(255),
					creation_time timestamp,
					magnitude double(8,2),
					location text,
					latitude varchar(255),
					longitude varchar(255),
					primary key(id)
				)
			";
			
			dbDelta($sql);
			dbDelta($sql_b);
		}
		//config del post custom
		public function quake_post(){
			return register_post_type( 'quake', [
				'label'                 => __( 'Quake', 'quake' ),
				'description'           => __( 'Quake', 'quake' ),
				'labels'                => $labels,
				'supports'              => array( ),
				//'taxonomies'            => array( 'category', 'post_tag' ),
				'hierarchical'          => false,
				'public'                => true,
				'show_ui'               => true,
				'show_in_menu'          => true,
				'menu_position'         => 5,
				'show_in_admin_bar'     => true,
				'show_in_nav_menus'     => true,
				'can_export'            => true,
				'has_archive'           => true,		
				'exclude_from_search'   => false,
				'publicly_queryable'    => true,
				'capability_type'       => 'post',
			]);
		}
		//quando disattivo il DB droppo le tabelle
		public function db_drop(){
			global $wpdb;
			$wpdb->query('drop table if exists earthquake');
			$wpdb->query('drop table if exists earthquake_config');
			//$wpdb->delete('wp_posts', array('post_type' => 'quake')) or die("impossibile cancellare");
			$wpdb->query("delete from wp_posts where post_type='quake'");
		}
		//config dei menu lato admin
		public function addAdminMenu(){
			add_menu_page( 'quake', 'Quake Notifier', '', 'quake');
			add_submenu_page('quake', 'Quake Config', 'Quake Config', 'manage_options', 'quake_config', array($this, 'adminQuake'));
			add_submenu_page('quake', 'Quake Update', 'Quake Update', 'manage_options', 'quake_update', array($this, 'updateQuake'));
		}
		//pagina di admin dei quakes
		public function adminQuake(){
			global $wpdb;
			$config = $wpdb->get_row('select * from earthquake_config');
			if(isset($_POST['m_min']) && isset($_POST['m_max'])){
				$wpdb->query('truncate earthquake_config');
				$wpdb->replace(
					'earthquake_config',[
						'min_magnitude' => $_POST['m_min'],
						'max_magnitude' => $_POST['m_max']
					]
				);
				?>
					<script type="text/javascript">
						document.location.href='?page=quake_config&op=success';
						<?php
							if($_GET['op']=='success'){
								?>
									alert("configurazione salvata");
								<?php
							}
						?>
					</script>
				<?php
			}
				?>
			<div class="row">
				<div class="col-md-12">
					&nbsp;
				</div>
			</div>
			<div class="row">
				<div class="col-md-6">
					<table class="table" border=1>
						<thead>
							<tr>
								<th colspan=2>Configurazione</th>
							</tr>
						</thead>
						<tbody>
							<form method="POST">
								<tr>
									<td>Magnitudo minima</td>
									<td>
										<select name="m_min" class="form-control">
											<?php
												for($i=1; $i<=9; $i++){
													$selected = '';
													if($i==$config->min_magnitude){
														$selected = ' selected';
													}
													echo '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
												}
											?>
										</select>
									</td>
								</tr>
								<tr>
									<td>Magnitudo massima</td>
									<td>
										<select name="m_max" class="form-control">
											<?php
												for($j=1; $j<=9; $j++){
													$selected = '';
													if($j==$config->max_magnitude){
														$selected = ' selected';
													}
													echo '<option value="'.$j.'" '.$selected.'>'.$j.'</option>';
												}
											?>
										</select>
									</td>
								</tr>	
								<tr>
									<td colspan=2 class="text-center">
										<button class="btn btn-info">SALVA</button>
									</td>
								</tr>
							</form>
						</tbody>
					</table>
				</div>
			</div>
			<?php
		}

		//pagina di update dei quakes
		public function updateQuake(){
			if(isset($_POST['op']) && $_POST['op']=='update'){
				global $wpdb;
				echo '
					<div class="row">
						<div class="col-md-6">
							recupero l\'elenco dei terremoti			
						</div>
					</div>
				';

				$this->getData();
				
				echo '
					<div class="row">
						<div class="col-md-6">
							aggiornamento completato
						</div>
					</div>
				';	
			}
			
			?>
				<form method="POST">
					<input type="hidden" name="op" id="op" value="update">
					<div class="row">
						<div class="col-md-6">
							Clicca qui sotto se vuoi forzare l'update dei terremoti
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<button class="btn btn-info">UPDATE</button>
						</div>
					</div>	
				</form>
				
			<?php
		}

		//leggo i dati dal core e aggiorno la lista degli eventi
		public function getData(){
			global $wpdb;
			$url = 'quake.local/api/quake/search';

			$res = $wpdb->get_results('select min_magnitude, max_magnitude from earthquake_config');
			$res = $res[0];

			$curl = curl_init( $url );
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt($curl, CURLOPT_VERBOSE, 0);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, 'min='.$res->min_magnitude.'&max='.$res->max_magnitude);
			$response = curl_exec( $curl );
			curl_close( $curl );
				
			$res = json_decode($response, true);

			$wpdb->delete('wp_posts', array('post_type' => 'quake'));

			if(is_array($res)){
				foreach ($res as $key => $event) {
					$wpdb->insert(
						'earthquake',[
							'id_earthquake' => $event['id_earthquake'],
							'creation_time' => $event['creation_time'],
							'magnitude' => $event['magnitude'],
							'location' => $event['location'],
							'latitude' => $event['latitude'],
							'longitude' => $event['longitude'],
						]
					) or die("impossibile creare");
					$id_post = wp_insert_post( [
						'post_author' => 'gaecamp@gmail.com'
						,'post_title' => $event['location']." - ".$event['magnitude']
						,'post_status' => 'publish'
						,'post_type' => 'quake'
					]) or die("impossibile creare il post_quake");
					
					add_post_meta($id_post, 'id_earthquake', $event['id_earthquake']);
					add_post_meta($id_post, 'creation_time', $event['creationTime']);
					add_post_meta($id_post, 'magnitude', $event['magnitude']);
					add_post_meta($id_post, 'location', $event['location']);
					add_post_meta($id_post, 'latitude', $event['latitude']);
					add_post_meta($id_post, 'longitude', $event['longitude']);

					$post_content = '
						<table class="table">
							<thead>
								
							</thead>
							<tbody>
								<tr>
									<td>
										ID Evento
									</td>
									<td>
										'.get_post_meta($id_post, 'id_earthquake')[0].'
									</td>
								</tr>
								</tr>
									<td>
										Luogo
									</td>
									<td>
										'.get_post_meta($id_post, 'location')[0].'
									</td>
								</tr>
								<tr>
									<td>
										Data
									</td>
									<td>
										'.get_post_meta($id_post, 'creation_time')[0].'
									</td>
								</tr>
								<tr>
									<td>
										Mangnitudo
									</td>
									<td>
										'.get_post_meta($id_post, 'magnitude')[0].'
									</td>
								</tr>
								<tr>
									<td colspan=2>
										'.get_post_meta($id_post, 'latitude')[0].' '.get_post_meta($id_post, 'longitude')[0].'
									</td>
								</tr>
							</tbody>
						</table>
					';
					wp_update_post( ['ID' => $id_post, 'post_content' => $post_content], $wp_error );
				}
			}
		}

		//creo la categoria dei quake
		public function addQuakeCategory(){
			wp_create_category('Quake', 0);
		}

	}
	register_activation_hook(__FILE__, array('Quake', 'db_update'));
	register_deactivation_hook(__FILE__, array('Quake', 'db_drop'));
	$q = new Quake();
 ?>