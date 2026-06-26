import os
import re

def process_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    new_content = content.replace('namespace core;', 'namespace Magma;')
    new_content = new_content.replace('namespace core\\', 'namespace Magma\\')
    new_content = new_content.replace('use core\\', 'use Magma\\')
    new_content = new_content.replace('\\core\\', '\\Magma\\')

    if new_content != content:
        with open(filepath, 'w') as f:
            f.write(new_content)
        print(f"Updated {filepath}")

for root, _, files in os.walk('magma'):
    for file in files:
        if file.endswith('.php'):
            process_file(os.path.join(root, file))

for root, _, files in os.walk('app'):
    for file in files:
        if file.endswith('.php'):
            process_file(os.path.join(root, file))

for root, _, files in os.walk('www'):
    for file in files:
        if file.endswith('.php'):
            process_file(os.path.join(root, file))
