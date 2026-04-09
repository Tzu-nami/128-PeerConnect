<?php use function Livewire\Volt\{layout, state, mount}; layout('layouts.app'); mount(function () { abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access'); }); ?>

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-user-secret text-gray-400"></i>
                    Anonymous Student Feedbacks
                </h2>
                <p class="text-xs text-gray-500">Student identities are hidden to encourage honest reporting.</p>
            </div>
            <div class="flex gap-3">
                <select class="table-filter-select">
                    <option>All Ratings</option>
                    <option>5 Stars</option>
                    <option>4 Stars</option>
                    <option>Below 3</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-gray-400 text-xs uppercase">
                    <tr>
                        <th class="px-6 py-4">Participant Ref</th>
                        <th class="px-6 py-4">Course/Year</th>
                        <th class="px-6 py-4">Comment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded text-gray-600">USR-8821</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-700 font-medium">BS Computer Science - 2nd Year</td>
                        <td class="px-6 py-4">
                            <p class="text-sm text-slate-600 italic">"The peer mentor was very patient with the coding exercises."</p>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded text-gray-600">USR-4509</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-700 font-medium">BS Information Tech - 1st Year</td>
                        <td class="px-6 py-4">
                            <p class="text-sm text-slate-600 italic">"Hard to hear the audio during the session."</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100 flex justify-between items-center bg-slate-50 text-xs text-gray-500">
            <span></span>
            <div class="flex gap-2">
                <button class="pagination-btn">Previous</button>
                <button class="pagination-btn bg-red-800 text-white border-red-800">1</button>
                <button class="pagination-btn">Next</button>
            </div>
            <span></span>
        </div>
    </div>
</div>
