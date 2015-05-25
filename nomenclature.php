<?php

require 'config.php';
dol_include_once('/product/class/product.class.php');
dol_include_once('/fourn/class/fournisseur.product.class.php');
dol_include_once('/core/class/html.formother.class.php');
dol_include_once('/core/lib/product.lib.php');
dol_include_once('/declinaison/class/declinaison.class.php');

llxHeader('','Nomenclature');

$product = new Product($db);
$product->fetch(GETPOST('fk_product'));

$head=product_prepare_head($product, $user);
$titre=$langs->trans('Nomenclature');
$picto=($product->type==1?'service':'product');
dol_fiche_head($head, 'nomenclature', $titre, 0, $picto);

headerProduct($product);

$TNomenclature = TNomenclature::get($product->id);


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
    
  
        
   
       
        
    
}
