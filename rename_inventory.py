import os
import re

def replace_in_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    # Update namespaces inside the moved files
    if 'modules/Inventory/models' in filepath:
        content = content.replace('namespace Magma\\models;', 'namespace Magma\\modules\\Inventory\\models;')
    elif 'modules/Inventory/services' in filepath:
        content = content.replace('namespace Magma\\services;', 'namespace Magma\\modules\\Inventory\\services;')
    elif 'modules/Inventory/domain' in filepath:
        content = content.replace('namespace Magma\\domain;', 'namespace Magma\\modules\\Inventory\\domain;')
    elif 'modules/Inventory/jobs' in filepath:
        content = content.replace('namespace Magma\\jobs;', 'namespace Magma\\modules\\Inventory\\jobs;')

    # Fix usages
    content = content.replace('Magma\\models\\InventoryLedgerRepositoryInterface', 'Magma\\modules\\Inventory\\models\\InventoryLedgerRepositoryInterface')
    content = content.replace('Magma\\models\\InventoryLedgerRepository', 'Magma\\modules\\Inventory\\models\\InventoryLedgerRepository')
    content = content.replace('Magma\\models\\VendorInventoryRepositoryInterface', 'Magma\\modules\\Inventory\\models\\VendorInventoryRepositoryInterface')
    content = content.replace('Magma\\models\\VendorInventoryRepository', 'Magma\\modules\\Inventory\\models\\VendorInventoryRepository')
    content = content.replace('Magma\\domain\\InventoryMovement', 'Magma\\modules\\Inventory\\domain\\InventoryMovement')
    content = content.replace('Magma\\jobs\\UpdateInventoryTotalsJob', 'Magma\\modules\\Inventory\\jobs\\UpdateInventoryTotalsJob')
    content = content.replace('Magma\\services\\InventoryService', 'Magma\\modules\\Inventory\\services\\InventoryService')

    with open(filepath, 'w') as f:
        f.write(content)

for root, _, files in os.walk('.'):
    if '.git' in root:
        continue
    for file in files:
        if file.endswith('.php'):
            replace_in_file(os.path.join(root, file))
