<?php

require 'config.php';
dol_include_once('/product/class/product.class.php');
dol_include_once('/fourn/class/fournisseur.product.class.php');
dol_include_once('/core/class/html.formother.class.php');
dol_include_once('/core/lib/product.lib.php');
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('/product/class/html.formproduct.class.php');

llxHeader('','Nomenclature');

$product = new Product($db);
$product->fetch(GETPOST('fk_product'));

$action= GETPOST('action');

$PDOdb=new TPDOdb;

if($action==='add_nomenclature') {
    
    $n=new TNomenclature;
    $n->set_values($_REQUEST);
    $n->save($PDOdb);
    
    
}
else if($action === 'delete_nomenclature_detail') {
    
    $n=new TNomenclature;
    $n->load($PDOdb, GETPOST('fk_nomenclature'));
    
    $n->TNomenclatureDet[GETPOST('k')]->to_delete = true;
    
    $n->save($PDOdb);
    
}
else if($action==='save_nomenclature') {
    
    $n=new TNomenclature;
    $n->load($PDOdb, GETPOST('fk_nomenclature'));
    $n->set_values($_POST);
    
    if(!empty($_POST['TNomenclature'])) {
        foreach($_POST['TNomenclature'] as $k=>$TDetValues) {
            
            $n->TNomenclatureDet[$k]->set_values($TDetValues);
                    
        }
        
        
    }
    
    $fk_new_product = (int)GETPOST('fk_new_product');
    if(GETPOST('add_nomenclature') && $fk_new_product>0) {
        
        $k = $n->addChild($PDOdb, 'TNomenclatureDet');
        
        $det = &$n->TNomenclatureDet[$k];
        
        $det->fk_product = $fk_new_product;
        
    }
    
    
    $n->save($PDOdb);
    
    
    
}


$head=product_prepare_head($product, $user);
$titre=$langs->trans('Nomenclature');
$picto=($product->type==1?'service':'product');
dol_fiche_head($head, 'nomenclature', $titre, 0, $picto);

headerProduct($product);

$form=new Form($db);

$TNomenclature = TNomenclature::get($PDOdb, $product->id);

foreach($TNomenclature as &$n) {

    $formCore=new TFormCore('auto', 'form_nom_'.$n->getId(), 'post', false);
    echo $formCore->hidden('action', 'save_nomenclature');
    echo $formCore->hidden('fk_nomenclature', $n->getId());
    echo $formCore->hidden('fk_product', $product->id);
    
    ?>
    <table class="liste" width="100%">
        <tr class="liste_titre">
            <td class="liste_titre"><?php echo $langs->trans('Nomenclature').' n°'.$n->getId() ?></td>
            <td class="liste_titre"><?php echo $formCore->texte($langs->trans('Title'), 'title', $n->title, 50,255) ?></td>
        </tr>
        <tr>
           <td colspan="2">
               <?php
               
               if(count($n->TNomenclatureDet>0)) {
                   
                   ?>
                   <table width="100%" class="liste">
                       <tr class="liste_titre">
                           <td class="liste_titre"><?php echo $langs->trans('Type'); ?></td>
                           <td class="liste_titre"><?php echo $langs->trans('Product'); ?></td>
                           <td class="liste_titre"><?php echo $langs->trans('Qty'); ?></td>
                           <td class="liste_titre">&nbsp;</td>
                       </tr>
                       <?php
                       $class='';
                       foreach($n->TNomenclatureDet as $k=>&$det) {
                           
                           $class = ($class == 'impair') ? 'pair' : 'impair';
                           
                           ?>
                           <tr class="<?php echo $class ?>">
                               <td><?php echo $formCore->combo('', 'TNomenclature['.$k.'][product_type]', TNomenclatureDet::$TType, $det->product_type) ?></td>
                               <td><?php 
                                    $p_nomdet = new Product($db);
                                    $p_nomdet->fetch($det->fk_product);
                                    
                                    echo $p_nomdet->getNomUrl(1).' '.$p_nomdet->label;
                                    
                               ?></td>    
                               <td><?php echo $formCore->texte('', 'TNomenclature['.$k.'][qty]', $det->qty, 7,100) ?></td>
                               <td><a href="?action=delete_nomenclature_detail&k=<?php echo $k ?>&fk_nomenclature=<?php echo $n->getId() ?>&fk_product=<?php echo $product->id ?>"><?php echo img_delete() ?></a></td>                         
                           </tr>
                           <?
                           
                       }
                       
                       ?>
                   </table>
                   
                   <?php
                   
               }
               
               ?>
           </td> 
            
        </tr>
        <tr>
            <td align="right" colspan="2">
                <div class="tabsAction">
                    <?php
                        print $form->select_produits('', 'fk_new_product', '', 0);
                    ?>
                   <div class="inline-block divButAction">
                    <input type="submit" name="add_nomenclature" class="butAction" value="<?php echo $langs->trans('AddProductNomenclature'); ?>" />
                   </div>
                   <div class="inline-block divButAction">
                    <input type="submit" name="save_nomenclature" class="butAction" value="<?php echo $langs->trans('SaveNomenclature'); ?>" />
                   </div>
                </div>
            </td>
        </tr>
    </table>
    <?php
    
    $formCore->end();
    
}


?>
<div class="tabsAction">
<div class="inline-block divButAction"><a href="?action=add_nomenclature&fk_product=<?php echo $product->id ?>" class="butAction"><?php echo $langs->trans('AddNomenclature'); ?></a></div>
</div>
<?php

dol_fiche_end();

  

llxFooter();
$db->close();


function headerProduct(&$object) {
   global $langs, $conf, $db; 
    
    $form = new Form($db);
        
    print '<table class="border" width="100%">';
    
    
    // Ref
    print '<tr>';
    print '<td width="15%">' . $langs->trans("Ref") . '</td><td colspan="2">';
    print $form->showrefnav($object, 'ref', '', 1, 'ref');
    print '</td>';
    print '</tr>';
    
    // Label
    print '<tr><td>' . $langs->trans("Label") . '</td><td>' . $object->libelle . '</td>';
    
    $isphoto = $object->is_photo_available($conf->product->multidir_output [$object->entity]);
    
    $nblignes = 5;
    if ($isphoto) {
        // Photo
        print '<td valign="middle" align="center" width="30%" rowspan="' . $nblignes . '">';
        print $object->show_photos($conf->product->multidir_output [$object->entity], 1, 1, 0, 0, 0, 80);
        print '</td>';
    }
    
    print '</tr>';
    
    
    // Status (to sell)
    print '<tr><td>' . $langs->trans("Status") . ' (' . $langs->trans("Sell") . ')</td><td>';
    print $object->getLibStatut(2, 0);
    print '</td></tr>';
    
    print "</table>\n";
    
  echo '<br />';
        
   
       
        
    
}
