<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { cancel, store } from '@/routes/api/orders';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const { user } = defineProps<{
    user: object;
}>();
console.log(user);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex gap-4">
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="font-bold">Balance: ${{ user.balance }}</h2>

                    <h2 class="mt-4 font-bold">Assets</h2>
                    <ul>
                        <li v-for="asset in user.assets" :key="asset.id">
                            {{ asset.symbol }}: {{ asset.amount }}
                        </li>
                    </ul>
                </div>

                <div
                    class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="font-bold">Order</h2>
                    <table class="mt-4 w-full border text-sm">
                        <thead>
                            <tr>
                                <th class="w-1/6 text-center">ID</th>
                                <th class="w-1/6 text-center">Symbol</th>
                                <th class="w-1/6 text-center">Side</th>
                                <th class="w-1/6 text-center">Price</th>
                                <th class="w-1/6 text-center">Amount</th>
                                <th class="w-1/6 text-center">Status</th>
                                <th class="w-1/6 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="order in user.orders"
                                :key="order.id"
                                class="border p-4 text-center"
                            >
                                <td class="border">{{ order.id }}</td>
                                <td class="border">{{ order.symbol }}</td>
                                <td class="border">{{ order.side }}</td>
                                <td class="border">{{ order.price }}</td>
                                <td class="border">{{ order.amount }}</td>
                                <td class="border">{{ order.status }}</td>
                                <td class="border">
                                    <Link
                                        :href="cancel({ id: order.id })"
                                        method="post"
                                        v-if="order.status === 'Open'"
                                        type="button"
                                        class="underline"
                                        >Cancel</Link
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                class="relative flex flex-1 justify-center rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
            >
                <Form
                    :action="store()"
                    class="relative mx-auto flex flex-col items-center justify-center gap-4 py-4 text-center"
                    #default="{ errors, hasErrors, processing, wasSuccessful }"
                >
                    <div
                        v-if="hasErrors"
                        class="absolute top-2 left-2 text-sm text-red-500 italic"
                    >
                        {{ errors }}
                    </div>

                    <select
                        name="symbol"
                        id="symbol"
                        class="h-9 w-full border border-input"
                    >
                        <option value="BTC">BTC</option>
                        <option value="ETH">ETH</option>
                    </select>
                    <select
                        name="side"
                        id="side"
                        class="h-9 w-full border border-input"
                    >
                        <option value="buy">Buy</option>
                        <option value="sell">Sell</option>
                    </select>
                    <Input
                        type="number"
                        name="price"
                        id="price"
                        placeholder="Price"
                    />
                    <Input
                        type=""
                        name="amount"
                        id="amount"
                        placeholder="Amount"
                    />
                    <Button :disabled="processing" type="submit"
                        >Place Order</Button
                    >

                    <div v-if="wasSuccessful">Order placed successfully!</div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
